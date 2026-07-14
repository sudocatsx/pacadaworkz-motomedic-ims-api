<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = Carbon::now();

        $suppliers = [
            [
                'name' => 'Supplier A',
                'contact_person' => 'John Doe',
                'email' => 'johndoe@suppliera.com',
                'phone' => '123-456-7890',
                'address' => '123 Main St, Anytown, USA',
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Supplier B',
                'contact_person' => 'Jane Smith',
                'email' => 'janesmith@supplierb.com',
                'phone' => '098-765-4321',
                'address' => '456 Oak Ave, Anytown, USA',
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Supplier C',
                'contact_person' => 'Peter Jones',
                'email' => 'peterjones@supplierc.com',
                'phone' => '555-555-5555',
                'address' => '789 Pine Ln, Anytown, USA',
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        DB::table('suppliers')->insert($suppliers);
    }
}
