<?php

namespace Database\Seeders;

use App\Models\Role;
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
        $roleIds = Role::query()
            ->whereIn('role_name', ['superadmin', 'admin', 'manager', 'staff'])
            ->pluck('id', 'role_name');

        $password = 'Pacadaworkz@2026!';
        $users = [
            [
                'account' => 'Owner',
                'role_name' => 'superadmin',
                'name' => 'Superadmin Pacada',
                'email' => 'superadminpacada@gmail.com',
                'first_name' => 'Superadmin',
                'last_name' => 'Pacada',
            ],
            [
                'account' => 'Developer',
                'role_name' => 'admin',
                'name' => 'Admin Pacada',
                'email' => 'asherjohn48@gmail.com',
                'first_name' => 'Admin',
                'last_name' => 'Pacada',
            ],
            [
                'account' => 'Manager',
                'role_name' => 'manager',
                'name' => 'Manager Pacada',
                'email' => 'managerpacada@gmail.com',
                'first_name' => 'Manager',
                'last_name' => 'Pacada',
            ],
            [
                'account' => 'Staff',
                'role_name' => 'staff',
                'name' => 'Staff Pacada',
                'email' => 'staffpacada@gmail.com',
                'first_name' => 'Staff',
                'last_name' => 'Pacada',
            ],
        ];

        $credentials = [];
        foreach ($users as $user) {
            $account = $user['account'];
            $roleName = $user['role_name'];
            unset($user['account']);
            unset($user['role_name']);
            $user['role_id'] = $roleIds->get($roleName)
                ?? throw new \RuntimeException("Role [{$roleName}] must be seeded before users.");
            $user['password'] = Hash::make($password);
            $user['is_active'] = true;

            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                $user
            );

            $credentials[] = [$account, $roleName, $user['name'], $user['email'], $password];
        }

        if ($this->command) {
            $this->command->newLine();
            $this->command->info('Demo account credentials');
            $this->command->table(
                ['Account', 'Role', 'Name', 'Email', 'Password'],
                $credentials
            );
        }
    }
}
