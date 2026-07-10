<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder; // Make sure this is imported
use Illuminate\Support\Facades\DB; // Make sure this is imported

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = Carbon::now();

        $brands = [
            [
                'name' => 'Honda',
                'description' => 'OEM Honda parts',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Yamaha',
                'description' => 'OEM Yamaha parts',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Suzuki',
                'description' => 'OEM Suzuki parts',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Kawasaki',
                'description' => 'OEM Kawasaki parts',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Racing Boy',
                'description' => 'Aftermarket performance parts',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'GIVI',
                'description' => 'Motorcycle accessories',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'NGK',
                'description' => 'Spark plugs and ignition',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Motul',
                'description' => 'Premium oils and lubricants',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Castrol',
                'description' => 'Engine oils',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Dunlop',
                'description' => 'Motorcycle tires',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        DB::table('brands')->insert($brands);
    }
}
