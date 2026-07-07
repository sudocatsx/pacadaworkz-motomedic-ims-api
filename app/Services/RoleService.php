<?php

namespace App\Services;

use App\Models\Role;

class RoleService
{

    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function getAllRoles()
    {
        return Role::with('permissions')->withCount('users')->get();
    }

    public function getRoleById($id)
    {

        return Role::with('permissions')->withCount('users')->findOrFail($id);
    }


    public function create(array $data)
    {
        $role = Role::create([
            'role_name' => $data['role_name'],
            'description' => $data['description']
        ]);

        $this->activityLogService->log(
            module: 'Role',
            action: 'Create',
            description: "Role created: {$role->role_name}",
            userId: auth()->id()
        );

        return $role->load('permissions')->loadCount('users');
    }



    public function update(array $data, $id)
    {

        $role = Role::findOrFail($id);
        $previousRole = $role->role_name;
        $role->update($data);

        $this->activityLogService->log(
            module: 'Role',
            action: 'Update',
            description: "Role name : {$previousRole} updated to: {$role->role_name}",
            userId: auth()->id()
        );

        return $role->load('permissions')->loadCount('users');
    }



    public function delete($id)
    {
        $role = Role::findOrFail($id);

        $roleName = $role->role_name;

        $role->delete();

        $this->activityLogService->log(
            module: 'Role',
            action: 'Delete',
            description: "Role deleted: {$roleName}",
            userId: auth()->id()
        );

        return true;
    }

}
