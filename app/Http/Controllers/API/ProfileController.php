<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Auth\UserNotFoundException;
use App\Http\Requests\Settings\Profile\UpdateProfileRequest;
use App\Http\Requests\Settings\Profile\UpdateThemeRequest;
use App\Http\Requests\Settings\Security\ChangePasswordRequest;
use App\Http\Resources\ProfileResource;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;

class ProfileController
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function showProfile()
    {
        $userId = Auth::id();

        try {
            $response = $this->userService->getUserById($userId);

            return response()->json([
                'success' => true,
                'data' => ProfileResource::make($response),
                'message' => 'User profile retrieved succesfully',
            ]);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Settings Profile [GET] Error: '.$e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ]);
        }
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $userId = Auth::id();
        try {
            $response = $this->userService->updateUserById($userId, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $response,
                'message' => 'User profile updated successfully',
            ]);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Settings Profile [PATCH] Error: '.$e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function updatePassword(ChangePasswordRequest $request)
    {
        $userId = Auth::id();
        try {
            $response = $this->userService->changePasswordById($userId, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Settings Profile Password [PATCH] Error: '.$e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function updateTheme(UpdateThemeRequest $request)
    {
        $userId = Auth::id();
        try {
            $this->userService->updateThemeById($userId, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Theme updated successfully',
            ]);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Settings Theme [PATCH] Error: '.$e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
