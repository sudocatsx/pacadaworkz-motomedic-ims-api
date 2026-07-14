<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSkuService
{
    public function generate(int $categoryId, int $brandId): string
    {
        return DB::transaction(function () use ($categoryId, $brandId) {
            // Lock the source records so requests for the same prefix allocate serially.
            $category = Category::query()->lockForUpdate()->findOrFail($categoryId);
            $brand = Brand::query()->lockForUpdate()->findOrFail($brandId);
            $prefix = $this->prefix($category->name, $brand->name);

            $sequence = DB::table('product_sku_sequences')
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            $number = $sequence ? ((int) $sequence->last_number + 1) : 1;

            if ($sequence) {
                DB::table('product_sku_sequences')
                    ->where('id', $sequence->id)
                    ->update(['last_number' => $number, 'updated_at' => now()]);
            } else {
                DB::table('product_sku_sequences')->insert([
                    'prefix' => $prefix,
                    'last_number' => $number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return sprintf('%s-%04d', $prefix, $number);
        });
    }

    private function prefix(string $categoryName, string $brandName): string
    {
        return $this->code($categoryName).'-'.$this->code($brandName);
    }

    private function code(string $name): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name)));

        return str_pad(substr($normalized, 0, 3), 3, 'X');
    }
}
