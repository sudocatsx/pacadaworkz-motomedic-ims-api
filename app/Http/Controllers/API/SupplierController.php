<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Controller;
use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierController extends Controller
{
    protected $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }

    public function index(Request $request)
    {
        try {
            $search = $request->query('search', null);
            $perPage = $request->query('per_page', 10);
            $suppliers = $this->supplierService->getAllSuppliers($search, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => SupplierResource::collection($suppliers),
                'meta' => [
                    'current_page' => $suppliers->currentPage(),
                    'per_page' => $suppliers->perPage(),
                    'total' => $suppliers->total(),
                    'last_page' => $suppliers->lastPage(),
                    'total_pages' => $suppliers->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' =>  'An error occured',
            ], 500);
        }
    }

    public function store(SupplierRequest $request)
    {
        try {
            $supplier = $this->supplierService->createSupplier($request->validated());
            return response()->json([
                'success' => true,
                'data' => new SupplierResource($supplier)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' =>  'An error occured',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $supplier = $this->supplierService->getSupplierById($id);
            return response()->json([
                'success' => true,
                'data' => new SupplierResource($supplier)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier not found'
            ], 404);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }

    public function update(SupplierRequest $request, $id)
    {
        try {
            $supplier = $this->supplierService->updateSupplier($request->validated(), $id);
            return response()->json([
                'success' => true,
                'data' => new SupplierResource($supplier)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $this->supplierService->deleteSupplier($id);
            return response()->json([
                'success' => true,
                'message' => 'Supplier deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' =>  'An error occured',
            ], 500);
        }
    }
}
