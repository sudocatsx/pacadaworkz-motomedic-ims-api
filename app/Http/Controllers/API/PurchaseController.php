<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Purchase\PurchaseReceiveException;
use App\Exceptions\Purchase\PurchaseUpdateException;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\API\Controller;
use App\Services\PurchaseService;
use App\Http\Resources\PurchaseOrdersResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Http\Requests\Purchase\PurchaseOrdersRequest;
use Exception;

class PurchaseController extends Controller
{
    protected $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    /**
     * Display a listing of the purchase orders.
     */
    public function index(Request $request)
    {
        try {
            $search = $request->query('search', null);
            $result = $this->purchaseService->getPurchases($search);

            return response()->json([
                'success' => true,
                'data' => PurchaseOrdersResource::collection($result),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'total_pages' => $result->lastPage(),
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }

    /**
     * Store a newly created purchase order in storage.
     */
    public function store(PurchaseOrdersRequest $request)
    {
        try {
            $result = $this->purchaseService->createPurchase($request->validated());
            return response()->json([
                'success' => true,
                'data' => new PurchaseOrdersResource($result)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                // 'message' => $e->getMessage()
                'message' => 'An error occurred'
            ], 500);
        }
    }

    /**
     * Display the specified purchase order.
     */
    public function show($id)
    {
        try {
            $result = $this->purchaseService->findPurchase($id);
            return response()->json([
                'success' => true,
                'data' => new PurchaseOrdersResource($result)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }

    /**
     * Update the specified purchase order in storage.
     */
    public function update(PurchaseOrdersRequest $request, $id)
    {
        try {
            $result = $this->purchaseService->updatePurchase($id, $request->validated());
            return response()->json([
                'success' => true,
                'data' => new PurchaseOrdersResource($result)
            ]);
        } catch (PurchaseUpdateException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode());
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }

    /**
     * Remove the specified purchase order from storage.
     */
    public function destroy($id)
    {
        try {
            $this->purchaseService->deletePurchase($id);
            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Purchase order deleted successfully'
                ]
            ]);
        } catch (PurchaseUpdateException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode());
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }

    /**
     * Mark purchase order as received.
     */
    public function receive($id)
    {
        $userId = Auth::id();
        try {
            $result = $this->purchaseService->receivePurchase($id, $userId);
            return response()->json([
                'success' => true,
                'data' => new PurchaseOrdersResource($result)
            ]);
        } catch (PurchaseReceiveException $e) {
            return response()->json([
                'success' => false,
                // 'message' => $e->getMessage()
            ], $e->getCode());
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
}
