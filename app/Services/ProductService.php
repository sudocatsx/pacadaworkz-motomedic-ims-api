<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProductService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly ProductImageService $productImageService,
    ) {}

    public function getAllProducts(array $filters = [])
    {
        $query = $this->productQuery($filters)
            ->select('products.*', 'inventory.quantity as current_stock')
            ->orderBy('products.name');

        return $query->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();
    }

    public function getProductSummary(array $filters = []): array
    {
        unset($filters['stock_status'], $filters['page'], $filters['per_page']);
        $row = $this->productQuery($filters)
            ->toBase()
            ->selectRaw('COUNT(products.id) as total_products')
            ->selectRaw('SUM(CASE WHEN COALESCE(inventory.quantity, 0) = 0 THEN 1 ELSE 0 END) as out_of_stock')
            ->selectRaw('SUM(CASE WHEN COALESCE(inventory.quantity, 0) > 0 AND COALESCE(inventory.quantity, 0) <= products.reorder_level THEN 1 ELSE 0 END) as low_stock')
            ->selectRaw('SUM(CASE WHEN COALESCE(inventory.quantity, 0) > products.reorder_level THEN 1 ELSE 0 END) as in_stock')
            ->first();

        return [
            'total_products' => (int) ($row->total_products ?? 0),
            'in_stock' => (int) ($row->in_stock ?? 0),
            'low_stock' => (int) ($row->low_stock ?? 0),
            'out_of_stock' => (int) ($row->out_of_stock ?? 0),
        ];
    }

    public function getProductById($id): Product
    {
        return Product::with(['category', 'brand', 'inventory', 'attribute_values.attribute'])->findOrFail($id);
    }

    public function create(array $data): Product
    {
        $imageData = $this->storeRequestedImage($data);

        try {
            return DB::transaction(function () use ($data, $imageData) {
                $product = Product::create([
                    'category_id' => $data['category_id'],
                    'brand_id' => $data['brand_id'],
                    'sku' => $data['sku'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'unit_price' => $data['unit_price'],
                    'cost_price' => $data['cost_price'],
                    'reorder_level' => $data['reorder_level'] ?? 10,
                    'is_active' => $data['is_active'] ?? true,
                    ...$imageData,
                ]);

                $openingStock = (int) ($data['initial_stock'] ?? 0);
                $product->inventory()->create([
                    'quantity' => $openingStock,
                    'location' => $data['location'] ?? null,
                    'last_stock_in' => $openingStock > 0 ? now() : null,
                ]);

                if ($openingStock > 0) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'user_id' => auth()->id(),
                        'movement_type' => 'in',
                        'quantity' => $openingStock,
                        'reference_type' => 'opening',
                        'reference_id' => $product->id,
                        'notes' => 'Opening stock',
                    ]);
                }

                $this->syncAttributeValues($product, $data['attribute_value_ids'] ?? []);
                $this->activityLogService->log(
                    module: 'Products',
                    action: 'Create',
                    description: "Created product: {$product->name} (SKU: {$product->sku})",
                    userId: auth()->id()
                );

                return $product->load(['category', 'brand', 'inventory', 'attribute_values.attribute']);
            });
        } catch (\Throwable $exception) {
            $this->productImageService->deleteManaged($imageData['image_url'] ?? null);
            throw $exception;
        }
    }

    public function update(array $data, $id): Product
    {
        $newImageData = $this->storeRequestedImage($data);
        $oldImagePath = null;

        try {
            $product = DB::transaction(function () use ($data, $id, $newImageData, &$oldImagePath) {
                $product = Product::findOrFail($id);
                $oldName = $product->name;
                $oldImagePath = $product->image_url;
                $attributeValueIds = $data['attribute_value_ids'] ?? null;
                $hasLocation = array_key_exists('location', $data);
                $location = $data['location'] ?? null;

                unset(
                    $data['attribute_value_ids'],
                    $data['location'],
                    $data['image'],
                    $data['image_source_url'],
                    $data['remove_image']
                );

                if ($newImageData !== []) {
                    $data = [...$data, ...$newImageData];
                } elseif (($data['remove_image'] ?? false) === true) {
                    $data = [...$data, ...$this->emptyImageData()];
                }

                $product->update($data);

                if ($hasLocation) {
                    $product->inventory()->updateOrCreate(
                        ['product_id' => $product->id],
                        ['location' => $location, 'quantity' => $product->inventory?->quantity ?? 0]
                    );
                }

                if (is_array($attributeValueIds)) {
                    $this->syncAttributeValues($product, $attributeValueIds);
                }

                $this->activityLogService->log(
                    module: 'Products',
                    action: 'Edit',
                    description: "Updated product: {$oldName} to {$product->name} (SKU: {$product->sku})",
                    userId: auth()->id()
                );

                return $product->load(['category', 'brand', 'inventory', 'attribute_values.attribute']);
            });

            if (($newImageData !== [] || ($data['remove_image'] ?? false)) && $oldImagePath !== $product->image_url) {
                $this->productImageService->deleteManaged($oldImagePath);
            }

            return $product;
        } catch (\Throwable $exception) {
            $this->productImageService->deleteManaged($newImageData['image_url'] ?? null);
            throw $exception;
        }
    }

    public function delete($id): bool
    {
        $product = Product::with('inventory')->findOrFail($id);

        if (
            $product->purchase_items()->exists()
            || $product->sales_items()->exists()
            || $product->stock_movements()->exists()
            || ($product->inventory && $product->inventory->quantity > 0)
        ) {
            throw new ConflictHttpException('Product cannot be deleted while stock or transaction records use it.');
        }

        $productName = $product->name;
        $imagePath = $product->image_url;
        $deleted = $product->delete();

        if ($deleted) {
            $this->productImageService->deleteManaged($imagePath);
            $this->activityLogService->log('Products', 'Delete', "Deleted product: {$productName}", auth()->id());
        }

        return $deleted;
    }

    public function createAttributeProduct(array $data, $id, $attributeId)
    {
        Attribute::findOrFail($attributeId);
        $product = Product::findOrFail($id);

        $this->activityLogService->log('Products', 'Edit', "Added attribute to product: {$product->name}", auth()->id());

        return ProductAttribute::updateOrCreate(
            ['product_id' => $id, 'attribute_value_id' => $data['attribute_value_id']],
            ['product_id' => $id, 'attribute_value_id' => $data['attribute_value_id']]
        );
    }

    public function getProductsForExport()
    {
        return Product::with(['category', 'brand', 'inventory', 'attribute_values.attribute'])->orderBy('name')->get();
    }

    public function deleteAttributeProduct($id, $attributeValueId)
    {
        $product = Product::findOrFail($id);
        $this->activityLogService->log('Products', 'Edit', "Removed attribute from product: {$product->name}", auth()->id());

        return ProductAttribute::where('product_id', $id)
            ->where('attribute_value_id', $attributeValueId)
            ->delete();
    }

    private function productQuery(array $filters): Builder
    {
        $query = Product::query()
            ->with(['category', 'brand', 'inventory', 'attribute_values.attribute'])
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('inventory', function ($join) {
                $join->on('inventory.product_id', '=', 'products.id')->whereNull('inventory.deleted_at');
            });

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = '%'.strtolower($search).'%';
            $query->where(function ($query) use ($needle) {
                $query->whereRaw('LOWER(products.name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(products.description) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(products.sku) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(categories.name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(brands.name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(inventory.location) LIKE ?', [$needle]);
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }
        if (! empty($filters['brand_id'])) {
            $query->where('products.brand_id', $filters['brand_id']);
        }
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('products.is_active', $filters['is_active']);
        }

        match ($filters['stock_status'] ?? null) {
            'out_of_stock' => $query->whereRaw('COALESCE(inventory.quantity, 0) = 0'),
            'low_stock' => $query->whereRaw('COALESCE(inventory.quantity, 0) > 0')->whereColumn('inventory.quantity', '<=', 'products.reorder_level'),
            'in_stock' => $query->whereColumn('inventory.quantity', '>', 'products.reorder_level'),
            default => null,
        };

        return $query;
    }

    private function storeRequestedImage(array $data): array
    {
        if (isset($data['image'])) {
            return $this->productImageService->storeUpload($data['image']);
        }
        if (! empty($data['image_source_url'])) {
            return $this->productImageService->storeRemote($data['image_source_url']);
        }
        if (($data['remove_image'] ?? false) === true) {
            return $this->emptyImageData();
        }

        return [];
    }

    private function emptyImageData(): array
    {
        return [
            'image_url' => null,
            'image_original_name' => null,
            'image_mime_type' => null,
            'image_size_bytes' => null,
            'image_source' => null,
        ];
    }

    private function syncAttributeValues(Product $product, array $attributeValueIds): void
    {
        $attributeValueIds = collect($attributeValueIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $query = ProductAttribute::where('product_id', $product->id);

        if ($attributeValueIds->isEmpty()) {
            $query->delete();

            return;
        }

        $query->whereNotIn('attribute_value_id', $attributeValueIds)->delete();
        foreach ($attributeValueIds as $attributeValueId) {
            ProductAttribute::updateOrCreate(
                ['product_id' => $product->id, 'attribute_value_id' => $attributeValueId],
                ['product_id' => $product->id, 'attribute_value_id' => $attributeValueId]
            );
        }
    }
}
