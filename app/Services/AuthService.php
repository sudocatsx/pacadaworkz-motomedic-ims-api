<?php

namespace App\Services;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    protected $activityLog;

    public function __construct(ActivityLogService $activityLog)
    {
        $this->activityLog = $activityLog;
    }

    // Login user and return token
    public function login(array $credentials)
    {
        // Attempt login
        $accessToken = auth('api')->setTTL(60)->attempt($credentials);

        if (! $accessToken) {
            throw new InvalidCredentialsException;
        }

        // Get authenticated user
        $user = auth('api')->user();

        $user->update(['last_login' => now()]);

        // Refresh token 15 days
        $refreshToken = auth('api')->setTTL(21600)->fromUser($user);

        // Log activity
        $this->activityLog->log(
            module: 'Authentication',
            action: 'login',
            description: 'User logged in',
            userId: $user->id
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    public function refresh()
    {
        $user = auth('api')->user();

        $accessToken = auth('api')->setTTL(60)->fromUser($user);

        return [
            'access_token' => $accessToken,
            'new_access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => 60 * 60,

        ];
    }

    public function logout()
    {
        $user = auth('api')->user(); // Save user BEFORE invalidating token

        JWTAuth::invalidate(JWTAuth::getToken());

        // Log logout
        $this->activityLog->log(
            module: 'Authentication',
            action: 'logout',
            description: 'User logged out',
            userId: $user->id
        );

        return true;
    }

    // Get authenticated user
    public function me()
    {
        $user = auth('api')->user();
        if ($user) {
            $user->load('role.permissions');
        }

        return $user;
    }
}
