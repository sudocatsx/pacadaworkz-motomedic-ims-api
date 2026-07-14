<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['Adjust Stock', 'Import', 'Export'] as $name) {
            DB::table('permissions')->updateOrInsert(
                ['module' => 'Products', 'name' => $name],
                [
                    'description' => $name.' Products',
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->copyGrant('Inventory', 'View', 'Products', 'View');
        $this->copyGrant('Inventory', 'Edit', 'Products', 'Adjust Stock');
        $this->copyGrant('Products', 'Create', 'Products', 'Import');
        $this->copyGrant('Products', 'View', 'Products', 'Export');

        $inventoryPermissionIds = DB::table('permissions')->where('module', 'Inventory')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $inventoryPermissionIds)->delete();
        DB::table('permissions')->whereIn('id', $inventoryPermissionIds)->delete();
    }

    public function down(): void
    {
        $now = now();
        foreach (['View', 'Create', 'Edit', 'Delete'] as $name) {
            DB::table('permissions')->updateOrInsert(
                ['module' => 'Inventory', 'name' => $name],
                [
                    'description' => $name.' Inventory',
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        DB::table('permissions')
            ->where('module', 'Products')
            ->whereIn('name', ['Adjust Stock', 'Import', 'Export'])
            ->delete();
    }

    private function copyGrant(string $sourceModule, string $sourceName, string $targetModule, string $targetName): void
    {
        $sourceId = DB::table('permissions')
            ->where('module', $sourceModule)
            ->where('name', $sourceName)
            ->value('id');
        $targetId = DB::table('permissions')
            ->where('module', $targetModule)
            ->where('name', $targetName)
            ->value('id');

        if (! $sourceId || ! $targetId) {
            return;
        }

        $roleIds = DB::table('role_permissions')
            ->where('permission_id', $sourceId)
            ->whereNull('deleted_at')
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $targetId],
                ['deleted_at' => null, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
};
