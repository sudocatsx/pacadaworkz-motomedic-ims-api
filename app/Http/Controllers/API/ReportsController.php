<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\Reports\ReportDateRangeRequest;
use App\Services\ReportsService;
use App\Services\SpreadsheetService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    protected $reportsService;

    protected $spreadsheetService;

    public function __construct(ReportsService $reportsService, SpreadsheetService $spreadsheetService)
    {
        $this->reportsService = $reportsService;
        $this->spreadsheetService = $spreadsheetService;
    }

    private function resolveDateRange(Request $request): array
    {
        $period = $request->query('period');

        if (! $period || $period === 'custom') {
            return [
                $request->query('start_date', null),
                $request->query('end_date', null),
            ];
        }

        $today = Carbon::today();

        return match ($period) {
            'daily' => [
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
            ],
            'weekly' => [
                $today->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay(),
                $today->copy()->endOfWeek(Carbon::SATURDAY)->endOfDay(),
            ],
            'monthly' => [
                $today->copy()->startOfMonth()->startOfDay(),
                $today->copy()->endOfMonth()->endOfDay(),
            ],
            'quarterly' => [
                $today->copy()->startOfQuarter()->startOfDay(),
                $today->copy()->endOfQuarter()->endOfDay(),
            ],
            'yearly' => [
                $today->copy()->startOfYear()->startOfDay(),
                $today->copy()->endOfYear()->endOfDay(),
            ],
            default => [
                $request->query('start_date', null),
                $request->query('end_date', null),
            ],
        };
    }

    // show all sales report
    public function showSalesReport(ReportDateRangeRequest $request)
    {
        try {

            [$start, $end] = $this->resolveDateRange($request);

            $result = $this->reportsService->getSalesReport($start, $end);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occured'], 500);
        }
    }

    // show all purchases
    public function showPurchases(ReportDateRangeRequest $request)
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);

            $result = $this->reportsService->getPurchases($start, $end);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occured'], 500);
        }

    }

    // show inventory
    public function showInventory(ReportDateRangeRequest $request)
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);

            $result = $this->reportsService->getInventory($start, $end);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occured'], 500);
        }
    }

    // show performance
    public function showPerformance(ReportDateRangeRequest $request)
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);

            $result = $this->reportsService->getPerformance($start, $end);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occured'], 500);
        }

    }

    // show stock adjustments
    public function showStockAdjustments(ReportDateRangeRequest $request)
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);

            $result = $this->reportsService->getStockAdjustments($start, $end);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occured'], 500);
        }

    }

    // profit loss
    public function showProfitLossReport(ReportDateRangeRequest $request)
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);

            $result = $this->reportsService->getProfitLossReport($start, $end);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function export($type, ReportDateRangeRequest $request)
    {
        try {
            [$start, $end] = $this->resolveDateRange($request);
            $format = strtolower($request->query('format', 'xlsx'));

            if ($format === 'csv') {
                $result = $this->reportsService->getReportCSV($start, $end, $type);

                return response($result, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="'.$type.'-report.csv"',
                ]);
            }

            $rows = $this->reportsService->getReportRows($start, $end, $type);
            $path = $this->spreadsheetService->createXlsx([
                'Report' => $rows,
            ]);

            return response()
                ->download($path, $type.'-report.xlsx', [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->deleteFileAfterSend(true);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
