<?php

namespace Database\Seeders;

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
            AttributeSeeder::class,

            // 2. Demo Accounts (Depends on Roles)
            UserSeeder::class,
        ]);
    }
}
