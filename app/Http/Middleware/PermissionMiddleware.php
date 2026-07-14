<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permission): Response
    {
        $user = auth('api')->user();

        if (! $user || ! $user->role) {
            return response()->json([
                'success' => false,
                'data' => [
                    'error' => 'Unauthorized',
                ],
            ], 401);
        }

        foreach ($permission as $permissions) {
            if (str_contains($permissions, '.')) {
                [$module, $name] = explode('.', $permissions, 2);
                if ($user->role->permissions->contains(
                    fn ($item) => $item->module === $module && $item->name === $name
                )) {
                    return $next($request);
                }

                continue;
            }

            if ($user->role->permissions->contains('name', $permissions)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'data' => [
                'error' => 'Forbidden',
            ],
        ], 403);

    }
}
