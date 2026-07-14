<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Sales\InvalidRefundSalesTransactionException;
use App\Exceptions\Sales\SalesTransactionNotFoundException;
// use App\Http\Requests\Sales\VoidTransactionRequest;
use App\Http\Requests\Sales\RefundTransactionRequest;
use App\Http\Resources\SalesTransactionResource;
use App\Services\SalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesController extends Controller
{
    protected $salesService;

    public function __construct(SalesService $salesService)
    {
        $this->salesService = $salesService;
    }

    public function index(Request $request)
    {
        try {
            $search = $request->query('search');
            // Extract specific filters
            $filters = $request->only(['user_id', 'payment_method', 'start_date', 'end_date']);

            // Call service
            $result = $this->salesService->getAllSales($search, $filters);

            return response()->json([
                'success' => true,
                'data' => SalesTransactionResource::collection($result),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'last_page' => $result->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Sales Get All Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                // 'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $result = $this->salesService->getSalesById($id);

            return response()->json([
                'success' => true,
                'data' => new SalesTransactionResource($result),
            ]);
        } catch (SalesTransactionNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sales transaction not found',
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Sales Get Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                // 'message' => $e->getMessage()
            ], 500);
        }
    }

    public function void(int $id)
    {
        try {
            $userId = Auth::id();
            // $validated = $request->validated();

            $result = $this->salesService->voidTransaction($userId, $id);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Sales transaction void successfully',
            ], 200);
        } catch (InvalidRefundSalesTransactionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (SalesTransactionNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sales transaction not found',
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Sales Void Transaction Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function refund(RefundTransactionRequest $request, int $id)
    {
        try {
            $userId = Auth::id();
            $data = $request->validated();

            $result = $this->salesService->refundTransaction($userId, $id, $data);

            return response()->json([
                'success' => true,
                'data' => new SalesTransactionResource($result),
                'message' => 'Sales transaction refunded successfully',
            ], 200);
        } catch (InvalidRefundSalesTransactionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?? 'Invalid refund sales transaction',
            ], $e->getCode());
        } catch (SalesTransactionNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?? 'Sales transaction not found',
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Sales Refund Transaction Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                // 'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function receipt(int $id)
    {
        try {
            $data = $this->salesService->getReceiptData($id);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (SalesTransactionNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sales transaction not found',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Sales Transaction Receipt Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
