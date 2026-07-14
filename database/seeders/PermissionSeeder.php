<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = Carbon::now();
        $permissions = [];

        $permissionSets = [
            'Dashboard' => ['View', 'View Financial Data', 'Create'],

            'Products' => ['View', 'Create', 'Edit', 'Adjust Stock', 'Delete', 'Import', 'Export'],
            'Categories' => ['View', 'Create', 'Edit', 'Delete'],
            'Brands' => ['View', 'Create', 'Edit', 'Delete'],
            'Attributes' => ['View', 'Create', 'Edit', 'Delete'],
            'Suppliers' => ['View', 'Create', 'Edit', 'Delete'],
            'Purchases' => ['View', 'Create', 'Edit', 'Delete'],
            'Users' => ['View', 'Create', 'Edit', 'Delete', 'Manage Lower Scope', 'Manage All'],
            'Roles' => ['View', 'Create', 'Edit', 'Delete'],

            'POS' => ['Access', 'Create Transaction', 'Request Discount', 'Authorize Discount'],

            'Transactions' => ['View', 'View Own', 'View All', 'Export', 'Request Refund', 'Request Void', 'Refund', 'Void'],

            'Reports' => ['View', 'Export'],

            'Activity Logs' => ['View Own', 'View All', 'Export'],

            'Settings' => ['View', 'Edit', 'Manage Database'],
        ];

        foreach ($permissionSets as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'name' => $action,
                    'description' => $action.' '.$module,
                    'module' => $module,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permission['name'],
                    'module' => $permission['module'],
                ],
                $permission
            );
        }
    }
}
