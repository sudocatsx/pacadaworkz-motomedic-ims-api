<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Auth\UserNotFoundException;
use App\Http\Requests\User\ResetPasswordUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

// use App\Models\User;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $params = $request->all();
        if (($roleIds = $this->manageableRoleIds($request)) !== null) {
            $params['allowed_role_ids'] = $roleIds;
        }
        $users = $this->userService->getAllUsers($params);

        // return UserResource::collection($users);
        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function assignableRoles(Request $request)
    {
        $ids = $this->manageableRoleIds($request);
        $query = Role::query()->orderBy('role_name');
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return response()->json(['success' => true, 'data' => $query->get(['id', 'role_name'])]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $id)
    {
        /**
         *  Wag delete baka, magamit
         * */
        // $user = $this->userService->getUserById($id);
        // return response()->json([
        //     'success' => $user ? true : false,
        //     'data' => UserResource::make($user)
        // ], $user ? 200 : 404);
        try {
            $this->assertManageableUser($request, $id);
            $user = $this->userService->getUserById($id);

            return response()->json([
                'success' => true,
                'data' => UserResource::make($user),
            ]);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (UserNotFoundException $e) {
            \Log::error('Settings Profile [GET] Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $fields = $request->validated();
        $this->assertAssignableRole($request, (int) $fields['role_id']);
        $user = $this->userService->createUser($fields);

        // return new UserResource($user);
        return response()->json([
            'success' => true,
            'data' => UserResource::make($user),
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        try {
            $this->assertManageableUser($request, $id);
            $fields = $request->validated();
            if (isset($fields['role_id'])) {
                $this->assertAssignableRole($request, (int) $fields['role_id']);
            }
            $user = $this->userService->updateUserById($id, $fields);

            return response()->json([
                'success' => true,
                'data' => UserResource::make($user),
            ]);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (UserNotFoundException $e) {
            \Log::error('Settings Profile [GET] Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {

        /**
         *  Wag delete baka, magamit
         * */

        // $user = $this->userService->getUserById($id);
        // if (!$user) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'user does not exist'
        //     ], 404);
        // }

        // $response = $this->userService->deleteUserById($id);
        // return response()->json([
        //     'success' => $response,
        //     'message' => "user with an id of [{$id}] is deleted successfully"
        // ], 200);

        try {
            $response = $this->userService->deleteUserById($id);

            return response()->json([
                'success' => $response,
                'message' => "User with an id of [{$id}] is deleted successfully",
            ]);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (UserNotFoundException $e) {
            \Log::error('Settings Profile [GET] Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function resetPassword(ResetPasswordUserRequest $request, int $id)
    {
        try {
            $this->assertManageableUser($request, $id);
            $response = $this->userService->resetPasswordById($id, $request->validated());

            return response()->json([
                'success' => $response,
                'message' => 'Password reset successfully',
            ], 200);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (UserNotFoundException $e) {
            \Log::error('Settings Profile [GET] Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function clearAuthorizationPin(Request $request, int $id)
    {
        $this->assertManageableUser($request, $id);
        $user = $this->userService->clearAuthorizationPinById($id, (int) $request->user('api')->id);

        return response()->json([
            'success' => true,
            'data' => UserResource::make($user->load('role')),
            'message' => 'Authorization PIN enrollment cleared.',
        ]);
    }

    private function manageableRoleIds(Request $request): ?array
    {
        $actor = $request->user('api')->loadMissing('role.permissions');
        if ($actor->hasPermission('Users', 'Manage All')) {
            return null;
        }
        abort_unless($actor->hasPermission('Users', 'Manage Lower Scope'), 403);
        $actorPermissions = $actor->role->permissions->map(fn ($permission) => "{$permission->module}.{$permission->name}");

        return Role::with('permissions')->get()->filter(function (Role $role) use ($actor, $actorPermissions) {
            if ($role->id === $actor->role_id) {
                return false;
            }
            $candidate = $role->permissions->map(fn ($permission) => "{$permission->module}.{$permission->name}");

            return $candidate->isNotEmpty() && $candidate->diff($actorPermissions)->isEmpty() && $candidate->count() < $actorPermissions->count();
        })->pluck('id')->all();
    }

    private function assertManageableUser(Request $request, int $id): void
    {
        $actor = $request->user('api');
        abort_if((int) $actor->id === $id && ! $actor->loadMissing('role.permissions')->hasPermission('Users', 'Manage All'), 403);
        $roleIds = $this->manageableRoleIds($request);
        if ($roleIds !== null) {
            abort_unless(User::whereKey($id)->whereIn('role_id', $roleIds)->exists(), 403);
        }
    }

    private function assertAssignableRole(Request $request, int $roleId): void
    {
        $roleIds = $this->manageableRoleIds($request);
        if ($roleIds !== null) {
            abort_unless(in_array($roleId, $roleIds, true), 403);
        }
    }
}
