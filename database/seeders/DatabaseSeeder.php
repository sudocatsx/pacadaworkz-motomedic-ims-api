<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            // 1. Core Foundations (No dependencies)
            SystemSettingSeeder::class, // <-- Global Configurations
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            SupplierSeeder::class, // <-- CRITICAL: Must be before Purchase Orders

            // 2. Secondary Foundations (Depends on Roles)
            UserSeeder::class,

            // 3. Inventory (Depends on Categories, Brands, and Suppliers)
            ProductSeeder::class,
            InventorySeeder::class,

            // 4. Transactions (Depends on Users, Suppliers, and Products)
            PurchaseOrdersSeeder::class,
            StockMovementsSeeder::class,
            StockAdjustmentsSeeder::class,
        ]);
        // User::factory(10)->create();

        // $this->call([RoleSeeder::class]);
        // $this->call([UserSeeder::class]);
        // $this->call([PermissionSeeder::class]);
        // $this->call([CategorySeeder::class]);
        // $this->call([BrandSeeder::class]);
        // $this->call([ProductSeeder::class]);
        // $this->call([InventorySeeder::class]);
        // $this->call([SupplierSeeder::class]);
        // $this->call([StockMovementsSeeder::class]);
        // $this->call([StockAdjustmentsSeeder::class]);
        // $this->call([PurchaseOrdersSeeder::class]);
    }
}
