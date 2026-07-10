<?php

namespace App\Http\Controllers\API;

use App\Services\RoleService;
use App\Http\Controllers\API\Controller;
use App\Http\Resources\RoleResource;
use App\Http\Requests\Role\RoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index()
    {
        try {
            $roles = $this->roleService->getAllRoles();

            return response()->json([
                'success' => true,
                'data' => RoleResource::collection($roles)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $role = $this->roleService->getRoleById($id);

            return response()->json([
                "success" => true,
                'data' => new RoleResource($role)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }

    public function store(RoleRequest $request)
    {
        try {
            $validated = $request->validated();
            $role = $this->roleService->create($validated);

            return response()->json([
                'success' => true,
                'data' => new RoleResource($role)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }

    public function update(UpdateRoleRequest $request, $id)
    {
        try {
            $validated = $request->validated();
            $role = $this->roleService->update($validated, $id);

            return response()->json([
                'success' => true,
                'data' => new RoleResource($role)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $this->roleService->delete($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Role deleted successfully'
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' =>  'An error occured',
            ], 500);
        }
    }
}
