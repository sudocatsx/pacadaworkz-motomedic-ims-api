<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseOrdersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = Carbon::now();

        $purchase_orders = [
            [
                'supplier_id' => 1,
                'user_id' => 1,
                'order_date' => $timestamp,
                'expected_delivery' => $timestamp,
                'total_amount' => 10000,
                'notes' => 'purchase test',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'supplier_id' => 2,
                'user_id' => 2,
                'order_date' => $timestamp,
                'expected_delivery' => $timestamp,
                'total_amount' => 15000,
                'notes' => 'purchase test',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],

            [
                'supplier_id' => 1,
                'user_id' => 1,
                'order_date' => $timestamp,
                'expected_delivery' => $timestamp,
                'total_amount' => 20000,
                'notes' => 'purchase test',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],

            [
                'supplier_id' => 1,
                'user_id' => 1,
                'order_date' => $timestamp,
                'expected_delivery' => $timestamp,
                'total_amount' => 10000,
                'notes' => 'purchase test',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],

            [
                'supplier_id' => 3,
                'user_id' => 3,
                'order_date' => $timestamp,
                'expected_delivery' => $timestamp,
                'total_amount' => 20000,
                'notes' => 'purchase test',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],

            [
                'supplier_id' => 1,
                'user_id' => 1,
                'order_date' => $timestamp,
                'expected_delivery' => $timestamp,
                'total_amount' => 25000,
                'notes' => 'purchase test',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        DB::table('purchase_orders')->insert($purchase_orders);
    }
}
