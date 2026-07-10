<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = Carbon::now();

        $products = [
            [
                'category_id' => 1, // Engine Parts
                'brand_id' => 1, // Honda
                'sku' => 'HND-PST-001',
                'name' => 'Honda Wave 125 Piston Kit',
                'description' => 'Original Honda piston kit for Wave 125',
                'cost_price' => 1200,
                'unit_price' => 1800,
                'reorder_level' => 10,
                'image_url' => null,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'category_id' => 2, // Electrical Parts
                'brand_id' => 2, // Yamaha
                'sku' => 'YMH-CDI-002',
                'name' => 'Yamaha Mio CDI Unit',
                'description' => 'Genuine Yamaha CDI for Mio series',
                'cost_price' => 800,
                'unit_price' => 1200,
                'reorder_level' => 10,
                'image_url' => null,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'category_id' => 4, // Oils & Lubricants
                'brand_id' => 8, // Motul
                'sku' => 'MTL-OIL-003',
                'name' => 'Motul 7100 4T 10W40 Engine Oil',
                'description' => 'Premium synthetic engine oil 1L',
                'cost_price' => 650,
                'unit_price' => 950,
                'reorder_level' => 10,
                'image_url' => null,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'category_id' => 2, // Electrical Parts
                'brand_id' => 7, // NGK
                'sku' => 'NGK-SPK-004',
                'name' => 'NGK Iridium Spark Plug',
                'description' => 'High performance iridium spark plug',
                'cost_price' => 250,
                'unit_price' => 400,
                'reorder_level' => 10,
                'image_url' => null,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'category_id' => 5, // Tires & Wheels
                'brand_id' => 10, // Dunlop
                'sku' => 'DNL-TR-005',
                'name' => 'Dunlop D404 Tire 80/90-17',
                'description' => 'Front tire for underbone motorcycles',
                'cost_price' => 1400,
                'unit_price' => 2100,
                'reorder_level' => 10,
                'image_url' => null,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        DB::table('products')->insert($products);
    }
}
