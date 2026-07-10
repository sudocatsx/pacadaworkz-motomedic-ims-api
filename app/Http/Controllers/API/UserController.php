<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Auth\UserNotFoundException;
use App\Http\Requests\User\ResetPasswordUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
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
        $users = $this->userService->getAllUsers($request->all());

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

    /**
     * Display the specified resource.
     */
    public function show(int $id)
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
        $user = $this->userService->createUser($request->validated());

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
            $user = $this->userService->updateUserById($id, $request->validated());

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
}
