<?php

namespace App\Http\Controllers\API;

use App\Services\ProductService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductAttributeResource;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Requests\Product\ProductUpdateRequest;
use App\Http\Requests\Product\ProductAttributeRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProductController
{


    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    //get all products
    public function index(Request $request)
    {

        try {
            $search = $request->query('search', null);
            $categoryId = $request->query('category_id', null);
            $brandId = $request->query('brand_id', null);
            $perPage = $request->query('per_page', 10);


            $result = $this->productService->getAllProducts($search, $categoryId, $brandId, $perPage);
            return response()->json([
                'success' => true,
                'data' => ProductResource::collection($result),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'last_page' => $result->lastPage(),
                    'total_pages' => $result->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }


    //get product by id

    public function show($id)
    {


        try {

            $result = $this->productService->getProductById($id);

            return response()->json(
                [
                    'success'  => true,
                    'data' => new ProductResource($result)
                ]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
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


    //store product

    public function store(ProductRequest $request)
    {


        try {

            $result = $this->productService->create($request->validated());

            return response()->json(
                [
                    'success'  => true,
                    'data' => new ProductResource($result)
                ]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
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



    //update product
    public function update(ProductUpdateRequest $request, $id)
    {


        try {


            $result = $this->productService->update($request->validated(), $id);

            return response()->json(
                [
                    'success'  => true,
                    'data' => new ProductResource($result)
                ]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
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


    //delete product


    public function destroy($id)
    {


        try {

            $result = $this->productService->delete($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Product deleted successfully'
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
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


    //store attribute to the product
    public function storeAttribute(ProductAttributeRequest $request, $id, $attributeId)
    {


        try {

            $result = $this->productService->createAttributeProduct($request->validated(), $id, $attributeId);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => new ProductAttributeResource($result)

                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute/Product not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }



    //delete attribute to the product
    public function destroyAttributeProduct($id, $attributeProductId)
    {
        try {

            $result = $this->productService->deleteAttributeProduct($id, $attributeProductId);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Product deleted successfully'
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }

    //export as csv
    public function export()
    {
        $products = $this->productService->getProductsForExport();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products.csv"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            fputcsv($file, ['ID', 'SKU', 'Name', 'Category', 'Brand', 'Unit Price', 'Cost Price', 'Description']);

            // Add data rows
            foreach ($products as $product) {
                fputcsv($file, [
                    $product->id,
                    $product->sku,
                    $product->name,
                    $product->category ? $product->category->name : '',
                    $product->brand ? $product->brand->name : '',
                    $product->unit_price,
                    $product->cost_price,
                    $product->description,
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
