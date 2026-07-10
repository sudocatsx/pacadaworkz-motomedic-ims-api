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
            'Dashboard' => ['View', 'Create'],

            'Inventory'   => ['View', 'Create', 'Edit', 'Delete'],
            'Products'    => ['View', 'Create', 'Edit', 'Delete'],
            'Categories'  => ['View', 'Create', 'Edit', 'Delete'],
            'Brands'      => ['View', 'Create', 'Edit', 'Delete'],
            'Attributes'  => ['View', 'Create', 'Edit', 'Delete'],
            'Suppliers'   => ['View', 'Create', 'Edit', 'Delete'],
            'Purchases'   => ['View', 'Create', 'Edit', 'Delete'],
            'Users'       => ['View', 'Create', 'Edit', 'Delete'],
            'Roles'       => ['View', 'Create', 'Edit', 'Delete'],

            'POS' => ['Access', 'Create Transaction'],

            'Reports' => ['View', 'Export'],

            'Activity Logs' => ['View Own', 'View All', 'Export'],

           
            'Settings' => ['View', 'Edit'],
        ];


        foreach ($permissionSets as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'name' => $action,
                    'description'     => $action . ' ' . $module,
                    'module'          => $module,
                    'created_at'      => $timestamp,
                    'updated_at'      => $timestamp,
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
