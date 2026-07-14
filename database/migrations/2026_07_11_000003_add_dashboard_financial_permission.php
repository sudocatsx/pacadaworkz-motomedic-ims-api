<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['module' => 'Dashboard', 'name' => 'View Financial Data'],
            [
                'description' => 'View Financial Data Dashboard',
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('module', 'Dashboard')
            ->where('name', 'View Financial Data')
            ->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('role_name', ['admin', 'superadmin'])
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['deleted_at' => null, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('module', 'Dashboard')
            ->where('name', 'View Financial Data')
            ->value('id');

        if ($permissionId) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
