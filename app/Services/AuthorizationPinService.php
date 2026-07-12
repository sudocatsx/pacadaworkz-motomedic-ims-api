<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthorizationPinService
{
    public function eligible(string $module, string $permission)
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('authorization_pin')
            ->whereHas('role.permissions', fn ($query) => $query
                ->where('module', $module)->where('name', $permission))
            ->orderBy('name')->get(['id', 'name']);
    }

    public function authorize(User $initiator, int $authorizerId, string $pin, string $module, string $permission): User
    {
        $key = "authorization-pin:{$initiator->id}:{$authorizerId}:{$module}:{$permission}";
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['pin' => 'Too many failed attempts. Try again in 15 minutes.']);
        }

        $authorizer = User::with('role.permissions')->whereKey($authorizerId)->where('is_active', true)->first();
        if (! $authorizer?->authorization_pin || ! $authorizer->hasPermission($module, $permission) ||
            ! Hash::check($pin, $authorizer->authorization_pin)) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages(['pin' => 'The authorization PIN is invalid.']);
        }

        RateLimiter::clear($key);

        return $authorizer;
    }
}
