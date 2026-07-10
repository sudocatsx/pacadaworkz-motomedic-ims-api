<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'role_id' => 1,
                'name' => 'Superadmin Domdom',
                'email' => 'domdomkenneth23@gmail.com',
                'password' => Hash::make('superadmin'),
                'first_name' => 'Super',
                'last_name' => 'admin',
            ],
            [
                'role_id' => 2,
                'name' => 'Admin Asher',
                'email' => 'asherjohn48@gmail.com',
                'password' => Hash::make('admin'),
                'first_name' => 'Admin',
                'last_name' => 'admin',
            ],
            [
                'role_id' => 3,
                'name' => 'Staff Sharks',
                'email' => 'sharkspin@gmail.com',
                'password' => Hash::make('staff'),
                'first_name' => 'Staff',
                'last_name' => 'staff',
            ],
            [
                'role_id' => 2,
                'name' => 'Admin Gab',
                'email' => 'johngabrielleofiangga@gmail.com',
                'password' => Hash::make('admin'),
                'first_name' => 'admin',
                'last_name' => 'second',
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
