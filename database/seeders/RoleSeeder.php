<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'role_name' => 'superadmin',
                'description' => 'Administrator with full access',
            ],
            [
                'role_name' => 'admin',
                'description' => 'Administrator with access',
            ],
            [
                'role_name' => 'staff',
                'description' => 'Staff with limited permissions',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['role_name' => $role['role_name']],
                [
                    ...$role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
