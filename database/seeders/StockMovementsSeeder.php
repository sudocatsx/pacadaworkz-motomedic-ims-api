<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockMovementsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = Carbon::now();

        $stockMovements = [
            [
                'product_id' => 1,
                'user_id' => 1,
                'movement_type' => 'in',
                'quantity' => 10,
                'reference_type' => 'adjustment',
                'reference_id' => 1,
                'notes' => 'whatssup naga',
                'created_at' => $timestamp,
            ],

            [
                'product_id' => 2,
                'user_id' => 1,
                'movement_type' => 'out',
                'quantity' => 10,
                'reference_type' => 'adjustment',
                'reference_id' => 1,
                'notes' => 'whatssup',
                'created_at' => $timestamp,
            ],

            [
                'product_id' => 3,
                'user_id' => 2,
                'movement_type' => 'in',
                'quantity' => 15,
                'reference_type' => 'adjustment',
                'reference_id' => 1,
                'notes' => 'whatssup naga',
                'created_at' => $timestamp,
            ],

            [
                'product_id' => 3,
                'user_id' => 1,
                'movement_type' => 'in',
                'quantity' => 10,
                'reference_type' => 'adjustment',
                'reference_id' => 1,
                'notes' => 'whatssup naga',
                'created_at' => $timestamp,
            ],
        ];

        DB::table('stock_movements')->insert($stockMovements);
    }
}
