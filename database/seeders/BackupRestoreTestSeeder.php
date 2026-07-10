<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BackupRestoreTestSeeder extends Seeder
{
    /**
     * Run the database seeds for testing backup/restore.
     * This seeds only the essential data needed to login as Superadmin
     * and perform the restore operation.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
        ]);
    }
}
