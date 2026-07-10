<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\Product\ProductAttributeRequest;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Requests\Product\ProductUpdateRequest;
use App\Http\Requests\Stocks\StockAdjustmentRequest;
use App\Http\Resources\ProductAttributeResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockAdjustmentResource;
use App\Http\Resources\StockMovementResource;
use App\Services\ProductService;
use App\Services\SpreadsheetService;
use App\Services\StocksService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProductController
{
    protected $productService;

    protected $spreadsheetService;

    public function __construct(
        ProductService $productService,
        SpreadsheetService $spreadsheetService,
        private readonly StocksService $stocksService,
    ) {
        $this->productService = $productService;
        $this->spreadsheetService = $spreadsheetService;
    }

    // get all products
    public function index(Request $request)
    {

        try {
            $filters = $request->validate([
                'search' => 'sometimes|nullable|string|max:100',
                'category_id' => 'sometimes|nullable|integer|exists:categories,id',
                'brand_id' => 'sometimes|nullable|integer|exists:brands,id',
                'is_active' => 'sometimes|nullable|boolean',
                'stock_status' => 'sometimes|nullable|in:in_stock,low_stock,out_of_stock',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|in:10,20,50,100',
            ]);

            $result = $this->productService->getAllProducts($filters);

            return response()->json([
                'success' => true,
                'data' => ProductResource::collection($result),
                'summary' => $this->productService->getProductSummary($filters),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'last_page' => $result->lastPage(),
                    'total_pages' => $result->lastPage(),
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // get product by id

    public function show($id)
    {

        try {

            $result = $this->productService->getProductById($id);

            return response()->json(
                [
                    'success' => true,
                    'data' => new ProductResource($result),
                ]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // store product

    public function store(ProductRequest $request)
    {

        try {

            $result = $this->productService->create($request->validated());

            return response()->json(
                [
                    'success' => true,
                    'data' => new ProductResource($result),
                ], 201
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // update product
    public function update(ProductUpdateRequest $request, $id)
    {

        try {

            $result = $this->productService->update($request->validated(), $id);

            return response()->json(
                [
                    'success' => true,
                    'data' => new ProductResource($result),
                ]
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // delete product

    public function destroy($id)
    {

        try {

            $result = $this->productService->delete($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Product deleted successfully',
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }

    public function movements(Request $request, int $id)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|in:10,20,50',
        ]);
        $result = $this->stocksService->getProductMovements($id, (int) ($validated['per_page'] ?? 10));

        return response()->json([
            'success' => true,
            'data' => StockMovementResource::collection($result),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ]);
    }

    public function adjustStock(StockAdjustmentRequest $request, int $id)
    {
        $result = $this->stocksService->createCountAdjustment($id, $request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'product' => new ProductResource($result['product']),
                'adjustment' => new StockAdjustmentResource($result['adjustment']),
                'movement' => new StockMovementResource($result['movement']),
            ],
        ], 201);
    }

    // store attribute to the product
    public function storeAttribute(ProductAttributeRequest $request, $id, $attributeId)
    {

        try {

            $result = $this->productService->createAttributeProduct($request->validated(), $id, $attributeId);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => new ProductAttributeResource($result),

                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute/Product not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // delete attribute to the product
    public function destroyAttributeProduct($id, $attributeProductId)
    {
        try {

            $result = $this->productService->deleteAttributeProduct($id, $attributeProductId);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Product deleted successfully',
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $products = $this->productService->getProductsForExport();
        $rows = $this->productExportRows($products);
        $format = strtolower($request->query('format', 'xlsx'));

        if ($format === 'csv') {
            return response($this->spreadsheetService->rowsToCsv($rows), 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="products.csv"',
            ]);
        }

        $path = $this->spreadsheetService->createXlsx([
            'Products' => $rows,
        ]);

        return response()
            ->download($path, 'products.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    private function productExportRows($products): array
    {
        $rows = [
            ['ID', 'SKU', 'Name', 'Category', 'Brand', 'Unit Price', 'Cost Price', 'Quantity', 'Stock Status', 'Reorder Level', 'Location', 'Active', 'Image URL', 'Description', 'Attributes'],
        ];

        foreach ($products as $product) {
            $rows[] = [
                $product->id,
                $product->sku,
                $product->name,
                $product->category ? $product->category->name : '',
                $product->brand ? $product->brand->name : '',
                $product->unit_price,
                $product->cost_price,
                $product->inventory?->quantity ?? 0,
                ($product->inventory?->quantity ?? 0) === 0
                    ? 'out_of_stock'
                    : (($product->inventory?->quantity ?? 0) <= $product->reorder_level ? 'low_stock' : 'in_stock'),
                $product->reorder_level,
                $product->inventory?->location,
                $product->is_active ? 'yes' : 'no',
                $product->image_url ? Storage::disk('public')->url(ltrim(str_replace('/storage/', '', $product->image_url), '/')) : '',
                $product->description,
                $product->attribute_values->map(fn ($value) => $value->attribute?->name.': '.$value->value)->join('; '),
            ];
        }

        return $rows;
    }
}
