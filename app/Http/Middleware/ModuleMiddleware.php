<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModuleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$modules): Response
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

        foreach ($modules as $module) {
            if ($user->role->permissions->contains('module', $module)) {
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
