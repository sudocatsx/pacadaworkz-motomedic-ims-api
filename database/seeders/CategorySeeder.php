<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $timestamp = Carbon::now();

        $categories = [
            [
                'name' => 'Engine Parts',
                'description' => 'Engine components and parts',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Electrical Parts',
                'description' => 'Electrical system components',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Body Parts',
                'description' => 'Motorcycle body and frame parts',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Oils & Lubricants',
                'description' => 'Engine oils and lubricants',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Tires & Wheels',
                'description' => 'Tires and wheel components',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Brake System',
                'description' => 'Brake pads, discs, and components',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Suspension',
                'description' => 'Suspension and shock absorbers',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Accessories',
                'description' => 'Motorcycle accessories',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Helmets',
                'description' => 'Safety helmets',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Apparel',
                'description' => 'Riding gear and apparel',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        DB::table('categories')->insert($categories);
    }
}
