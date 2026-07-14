<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Http\Resources\AuthUserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        try {
            $credentials = $request->only('email', 'password');
            $tokens = $this->authService->login($credentials);

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $tokens['access_token'],
                    'expires_in' => auth('api')->factory()->getTTL() * 60,
                    'token_type' => 'bearer',
                    'refresh_token' => $tokens['refresh_token'],
                ],
            ]);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function refresh()
    {
        try {
            $refresh = $this->authService->refresh();

            return response()->json([
                'success' => true,
                'data' => $refresh,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function logout(Request $request)
    {
        try {
            $this->authService->logout();

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Successfully logged out',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function me()
    {
        try {
            $me = $this->authService->me();

            return response()->json([
                'success' => true,
                'data' => AuthUserResource::make($me),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        }
    }
}
