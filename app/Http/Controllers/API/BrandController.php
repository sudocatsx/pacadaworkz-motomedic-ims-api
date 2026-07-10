<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\BrandRequest;
use App\Http\Resources\BrandResource;
use App\Services\BrandService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BrandController
{
    protected $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    // get all brands
    public function index(Request $request)
    {
        try {

            $search = $request->query('search', null);
            $perPage = $request->query('per_page', 10);
            $result = $this->brandService->getAllBrands($search, $perPage);

            return response()->json([
                'success' => true,
                'data' => BrandResource::collection($result),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'last_page' => $result->lastPage(),
                    'total_pages' => $result->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error is occurred',
            ], 500);
        }
    }

    // get brand by id
    public function show($id)
    {

        try {
            $result = $this->brandService->getBrandById($id);

            return response()->json(
                [
                    'success' => true,
                    'data' => new BrandResource($result),
                ]
            );

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error is occurred',
            ], 500);
        }

    }

    // store Brand

    public function store(BrandRequest $request)
    {

        try {

            $result = $this->brandService->create($request->validated());

            return response()->json([
                'success' => true,
                'data' => new BrandResource($result),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error is occurred',
            ], 500);
        }

    }

    // update brand

    public function update(BrandRequest $request, $id)
    {

        try {
            $result = $this->brandService->update($request->validated(), $id);

            return response()->json([
                'success' => true,
                'data' => new BrandResource($result),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error is occurred',
            ], 500);
        }
    }

    // delete brand

    public function destroy($id)
    {

        try {

            $this->brandService->delete($id);

            return response()->json([
                'success' => true,
                'data' => 'Brand deleted succesfully',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found.',
            ], 404);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error is occurred',
            ], 500);
        }

    }
}
