<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Auth\InvalidGoogleTokenException;
use App\Exceptions\Auth\UserNotFoundException;
use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Resources\UserResource;
use App\Services\GoogleAuthService;
use Illuminate\Support\Facades\Log;

class GoogleAuthController
{
    protected GoogleAuthService $googleAuthService;

    public function __construct(GoogleAuthService $googleAuthService)
    {
        $this->googleAuthService = $googleAuthService;
    }

    public function login(GoogleAuthRequest $request)
    {
        $validated = $request->validated();
        try {
            // $validated['credential'] ay yung `id_token` from the frontend which is response from google-oauth
            $result = $this->googleAuthService->authenticate($validated['credential']);

            return response()->json([
                'success' => true,
                'data' => [
                    // 'user' => UserResource::make($result['user']),
                    'access_token' => $result['access_token'],
                    'refresh_token' => $result['refresh_token'],
                ],
            ], 200);
        } catch (InvalidGoogleTokenException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (UserNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            // Uncomment niyo for debugging
            Log::error('Google login failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed. Please try again.',
            ], 500);
        }
    }
}
