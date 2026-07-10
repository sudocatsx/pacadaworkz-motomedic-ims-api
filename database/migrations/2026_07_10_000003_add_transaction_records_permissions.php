<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['View', 'Export', 'Refund', 'Void'] as $name) {
            DB::table('permissions')->updateOrInsert(
                ['module' => 'Transactions', 'name' => $name],
                [
                    'description' => $name.' Transactions',
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $roleGrants = [
            'superadmin' => ['View', 'Export', 'Refund', 'Void'],
            'admin' => ['View', 'Export', 'Refund', 'Void'],
            'staff' => ['View'],
        ];

        foreach ($roleGrants as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('role_name', $roleName)->whereNull('deleted_at')->value('id');
            if (! $roleId) {
                continue;
            }

            $permissionIds = DB::table('permissions')
                ->where('module', 'Transactions')
                ->whereIn('name', $permissionNames)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['deleted_at' => null, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('module', 'Transactions')
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
