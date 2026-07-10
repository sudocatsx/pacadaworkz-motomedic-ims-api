<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {

        $user = auth('api')->user();

        if (! $user) {

            return response()->json([
                'success' => false,
                'data' => [
                    'error' => 'Unauthorized',
                ],
            ], 401);

        }

        if (! $user->role || ! in_array($user->role->role_name, $roles)) {

            return response()->json([
                'success' => false,
                'data' => [
                    'error' => 'Forbidden',
                ],
            ], 403);
        }

        return $next($request);
    }
}
