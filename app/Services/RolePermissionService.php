<?php
namespace App\Services;
use App\Models\RolePermission;
use App\Models\Role;
class RolePermissionService{

    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

public function assignPermissions($roleId, array $permissions)
{
    $role = Role::findOrFail($roleId);

    $role->permissions()->sync($permissions);


    $role->load('permissions');

    $this->activityLogService->log(
        module: 'RolePermission',
        action: 'Assign',
        description: "Assigned permissions to role: {$role->role_name}",
        userId: auth()->id()
    );

    return [
        'message' => 'Permissions assigned successfully.',
         'role_name'=> $role->role_name,
        'permissions' => $role->permissions()->get(),
    ];
}



}
