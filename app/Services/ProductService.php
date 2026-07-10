<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Attribute;
use App\Models\ProductAttribute;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProductService
{

    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }



    // get all products
    public function getAllProducts($search = null, $categoryId = null, $brandId = null, $perPage = 10)
    {
        $query = Product::query()
            ->with(['category', 'brand', 'inventory', 'attribute_values.attribute'])
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('inventory', 'inventory.product_id', '=', 'products.id')
            ->select('products.*', 'inventory.quantity as current_stock');

        if ($search) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(products.name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(products.description) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(products.sku) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(categories.name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(brands.name) LIKE ?', ["%{$search}%"]);
            });
        }

        if (!empty($categoryId)) {
            $query->where('products.category_id', $categoryId);
        }

        if (!empty($brandId)) {
            $query->where('products.brand_id', $brandId);
        }

        return $query->paginate($perPage)->withQueryString();
    }


    //get products by if
    public function getProductById($id)
    {

        return Product::with(['category', 'brand', 'inventory', 'attribute_values.attribute'])->findOrFail($id);
    }



    // create  products
    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create([
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'],
                'sku' => $data['sku'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'unit_price' => $data['unit_price'],
                'cost_price' => $data['cost_price'],
                'reorder_level' => $data['reorder_level'] ?? 10,
            ]);

            // TODO: entry sa attribute table

            // Create inventory entry
            $product->inventory()->create([
                'quantity' => $data['initial_stock'] ?? 0,
                'location' => $data['location'] ?? null,
                'last_stock_in' => ($data['initial_stock'] ?? 0) > 0 ? now() : null,
            ]);

            $this->syncAttributeValues($product, $data['attribute_value_ids'] ?? []);

            // Log activity
            $this->activityLogService->log(
                module: 'Products',
                action: 'Create',
                description: "Created product: {$product->name} (SKU: {$product->sku}) and initialized inventory",
                userId: auth()->id()
            );
            return $product->load(['category', 'brand', 'inventory', 'attribute_values.attribute']);
        });
    }


    //update product
    public function update(array $data, $id)
    {

        return DB::transaction(function () use ($data, $id) {
            $product = Product::findOrFail($id);
            $oldName = $product->name;
            $attributeValueIds = $data['attribute_value_ids'] ?? null;
            $inventoryData = [
                'quantity' => $data['initial_stock'] ?? null,
                'location' => $data['location'] ?? null,
            ];
            unset($data['attribute_value_ids']);
            unset($data['initial_stock'], $data['location']);

            $product->update($data);

            if ($inventoryData['quantity'] !== null || $inventoryData['location'] !== null) {
                $product->inventory()->updateOrCreate(
                    ['product_id' => $product->id],
                    array_filter($inventoryData, fn ($value) => $value !== null)
                );
            }

            if (is_array($attributeValueIds)) {
                $this->syncAttributeValues($product, $attributeValueIds);
            }

            // Log activity
            $this->activityLogService->log(
                module: 'Products',
                action: 'Edit',
                description: "Updated product: {$oldName} to {$product->name} (SKU: {$product->sku})",
                userId: auth()->id()
            );

            return $product->load(['category', 'brand', 'inventory', 'attribute_values.attribute']);
        });
    }
    //delete product
    public function delete($id)
    {

        $product = Product::findOrFail($id);
        $productName = $product->name;

        if (
            $product->purchase_items()->exists()
            || $product->sales_items()->exists()
            || $product->stock_movements()->exists()
            || ($product->inventory && $product->inventory->quantity > 0)
        ) {
            throw new ConflictHttpException('Product cannot be deleted while stock or transaction records use it.');
        }

        $result = $product->delete();

        if ($result) {
            // Log activity if deletion was successful
            $this->activityLogService->log(
                module: 'Products',
                action: 'Delete',
                description: "Deleted product: {$productName}",
                userId: auth()->id()
            );
        }

        return $result;
    }

    //create attribute in product

    public function createAttributeProduct(array $data, $id, $attributeId)
    {

        $attribute = Attribute::findOrFail($attributeId);

        $product = Product::findOrFail($id);
        $product_name = $product->name;
        if (!$attribute)
            return $attribute;
        else {
            $this->activityLogService->log(
                module: 'Products',
                action: 'Add attribute',
                description: "Add attribute to product:{$product_name}",
                userId: auth()->id()
            );
        }


        return ProductAttribute::updateOrCreate(
            ['product_id' => $id, 'attribute_value_id' => $data['attribute_value_id']],
            ['product_id' => $id, 'attribute_value_id' => $data['attribute_value_id']]
        );
    }


    //get all products for export
    public function getProductsForExport()
    {
        return Product::with(['category', 'brand'])->get();
    }


    //delete Attribute product

    public function deleteAttributeProduct($id, $attributeValueId)
    {

        $product = Product::findOrFail($id);
        $product_name = $product->name;

        $this->activityLogService->log(
            module: 'Products',
            action: 'deleted attribute',
            description: "delete attribute to product:{$product_name}",
            userId: auth()->id()
        );
        return ProductAttribute::where('product_id', $id)
            ->where('attribute_value_id', $attributeValueId)
            ->delete();
    }

    private function syncAttributeValues(Product $product, array $attributeValueIds): void
    {
        $attributeValueIds = collect($attributeValueIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

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
