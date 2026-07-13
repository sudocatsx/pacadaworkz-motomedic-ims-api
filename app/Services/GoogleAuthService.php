<?php

namespace App\Services;

use App\Exceptions\Auth\InvalidGoogleTokenException;
use App\Exceptions\Auth\UserNotFoundException;
use App\Models\User;
use Google_Client;
use Illuminate\Support\Facades\Log;

class GoogleAuthService
{
    private Google_Client $googleClient;

    protected ActivityLogService $activityLog;

    public function __construct(ActivityLogService $activityLog)
    {
        $this->googleClient = new Google_Client([
            'client_id' => config('services.google.client_id'),
        ]);
        $this->activityLog = $activityLog; // activitylog service
    }

    public function authenticate(string $credential)
    {

        // 1. verify credential token galing ito sa frontend
        $payload = $this->verifyGoogleToken($credential);

        $email = $payload['email'];
        $googleId = $payload['sub'];
        // $name = $payload['name'] ?? null;

        // 2. hanapin ang user via email
        $user = User::where('email', $email)->first();
        if (! $user) {
            throw new UserNotFoundException($email);
        }

        // 3. update google_id field ni user
        $user->update([
            'google_id' => $googleId,
            'last_login' => now(),
        ]);

        $tokens = $this->generateJWTTokens($user);
        $this->activityLog->log(
            module: 'Authentication',
            action: 'login',
            description: 'User logged in via Google',
            userId: $user->id
        );

        return [
            'user' => $user,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ];
    }

    private function verifyGoogleToken(string $credential)
    {
        try {
            $payload = $this->googleClient->verifyIdToken($credential);
            if (! $payload) {
                throw new InvalidGoogleTokenException;
            }

            return $payload;
        } catch (\Exception $e) {
            Log::error('Google token verification failed', [
                'error' => $e->getMessage(),
            ]);

            throw new InvalidGoogleTokenException;
        }
    }

    private function generateJWTTokens(User $user): array
    {
        $accessToken = auth('api')->setTTL(60)->fromUser($user);
        $refreshToken = auth('api')->setTTL(21600)->fromUser($user);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }
}
