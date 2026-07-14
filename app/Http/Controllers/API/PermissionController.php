<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\PermissionResource;
use App\Services\PermissionService;

class PermissionController extends Controller
{
    //

    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function index()
    {
        try {
            $permissions = $this->permissionService->getAllPermissions();

            return response()->json([
                'success' => true,
                'data' => PermissionResource::collection($permissions),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
            ], 500);
        }
    }
}
