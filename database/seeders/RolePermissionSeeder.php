<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = Permission::all();
        $permissionsByModule = $permissions->groupBy('module');

        $permissionIds = function (array $matrix) use ($permissionsByModule) {
            return collect($matrix)->flatMap(function (array $actions, string $module) use ($permissionsByModule) {
                return $permissionsByModule
                    ->get($module, collect())
                    ->whereIn('name', $actions)
                    ->pluck('id');
            })->values();
        };

        $roleMatrices = [
            'superadmin' => $permissions->pluck('id'),
            'admin' => $permissionIds([
                'Dashboard' => ['View', 'View Financial Data', 'Create'],
                'Products' => ['View', 'Create', 'Edit', 'Adjust Stock', 'Delete', 'Import', 'Export'],
                'Categories' => ['View', 'Create', 'Edit', 'Delete'],
                'Brands' => ['View', 'Create', 'Edit', 'Delete'],
                'Attributes' => ['View', 'Create', 'Edit', 'Delete'],
                'Suppliers' => ['View', 'Create', 'Edit', 'Delete'],
                'Purchases' => ['View', 'Create', 'Edit', 'Delete'],
                'Users' => ['View', 'Create', 'Edit', 'Delete'],
                'Roles' => ['View', 'Create', 'Edit', 'Delete'],
                'POS' => ['Access', 'Create Transaction'],
                'Transactions' => ['View', 'Export', 'Refund', 'Void'],
                'Reports' => ['View', 'Export'],
                'Activity Logs' => ['View All', 'Export'],
                'Settings' => ['View', 'Edit'],
            ]),
            'staff' => $permissionIds([
                'Dashboard' => ['View'],
                'Products' => ['View'],
                'Purchases' => ['View', 'Create'],
                'POS' => ['Access', 'Create Transaction'],
                'Transactions' => ['View'],
            ]),
        ];

        foreach ($roleMatrices as $roleName => $permissionIds) {
            $role = Role::where('role_name', $roleName)->first();
            if ($role) {
                $role->permissions()->sync($permissionIds);
            }
        }
    }
}
