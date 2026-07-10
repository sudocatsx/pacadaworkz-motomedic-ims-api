<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Sales\InvalidRefundSalesTransactionException;
use App\Exceptions\Sales\SalesTransactionNotFoundException;
use App\Http\Requests\Sales\RefundTransactionRequest;
use App\Http\Resources\SalesTransactionResource;
use App\Services\SalesService;
use App\Services\SimpleXlsxService;
use App\Services\TransactionRecordsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionRecordsService $records,
        private SalesService $sales,
        private SimpleXlsxService $xlsx,
    ) {}

    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $result = $this->records->paginate($filters);

        return response()->json([
            'success' => true,
            'data' => SalesTransactionResource::collection($result),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
                'summary' => $this->records->summary($filters),
                'filter_options' => [
                    'statuses' => ['completed', 'partially_refunded', 'refunded', 'voided'],
                    'payment_methods' => ['cash', 'gcash', 'card'],
                ],
            ],
        ]);
    }

    public function show(int $id)
    {
        return response()->json([
            'success' => true,
            'data' => new SalesTransactionResource($this->sales->getSalesById($id)),
        ]);
    }

    public function dailyReport(Request $request)
    {
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['success' => true, 'data' => $this->records->dailyReport($validated['date'])]);
    }

    public function export(Request $request)
    {
        $filters = $this->validatedFilters($request, true);
        $format = $request->validate(['format' => ['required', 'in:csv,xlsx']])['format'];
        $transactions = $this->records->export($filters);
        $rows = [[
            'Transaction ID', 'Date & Time', 'Cashier', 'Payment Method', 'Status', 'Gross Sales',
            'Discount', 'Refund', 'Net Sales', 'Amount Tendered', 'Change',
        ]];
        foreach ($transactions as $transaction) {
            $rows[] = [
                $transaction->transaction_no,
                $transaction->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                optional($transaction->user)->name ?? 'Unknown',
                strtoupper($transaction->payment_method),
                ucwords(str_replace('_', ' ', $transaction->status)),
                (float) $transaction->subtotal,
                (float) $transaction->discount,
                (float) $transaction->refund_amount,
                max(0, (float) $transaction->total_amount - (float) $transaction->refund_amount),
                (float) $transaction->amount_tendered,
                (float) $transaction->change,
            ];
        }

        $filename = 'transaction-records-'.now()->format('Y-m-d').'.'.$format;
        if ($format === 'xlsx') {
            $path = $this->xlsx->create($rows);

            return response()->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        return new StreamedResponse(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function receipt(int $id)
    {
        return response()->json(['success' => true, 'data' => $this->sales->getReceiptData($id)]);
    }

    public function refund(RefundTransactionRequest $request, int $id)
    {
        try {
            $transaction = $this->sales->refundTransaction((int) $request->user('api')->id, $id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => new SalesTransactionResource($transaction->load(['user', 'sales_items.product'])),
                'message' => 'Transaction refunded successfully.',
            ]);
        } catch (SalesTransactionNotFoundException $exception) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        } catch (InvalidRefundSalesTransactionException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function void(Request $request, int $id)
    {
        try {
            $transaction = $this->sales->voidTransaction((int) $request->user('api')->id, $id);

            return response()->json([
                'success' => true,
                'data' => new SalesTransactionResource($transaction->load(['user', 'sales_items.product'])),
                'message' => 'Transaction voided successfully.',
            ]);
        } catch (SalesTransactionNotFoundException $exception) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        } catch (InvalidRefundSalesTransactionException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    private function validatedFilters(Request $request, bool $export = false): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:completed,partially_refunded,refunded,voided'],
            'payment_method' => ['nullable', 'in:cash,gcash,card'],
            'period' => ['nullable', 'in:all,today,week,month,quarter,year,custom'],
            'start_date' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'page' => $export ? ['prohibited'] : ['nullable', 'integer', 'min:1'],
            'per_page' => $export ? ['prohibited'] : ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
