<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\Role\RolesPermissionRequest;
use App\Http\Resources\RolePermissionResource;
use App\Services\RolePermissionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RolePermissionController extends Controller
{
    protected $rolePermissionService;

    public function __construct(RolePermissionService $rolePermissionService)
    {
        $this->rolePermissionService = $rolePermissionService;
    }

    /**
     * Assign permissions to a role.
     *
     * @param  int  $id  Role ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignPermissions(RolesPermissionRequest $request, $id)
    {
        try {
            $result = $this->rolePermissionService->assignPermissions($id, $request->permissions);

            $permissionsResource = RolePermissionResource::collection($result['permissions']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'role_name' => $result['role_name'],
                    'permissions' => $permissionsResource,
                ],
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }
}
