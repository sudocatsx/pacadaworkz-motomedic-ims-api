<?php

use App\Http\Middleware\ActivityLogMiddleware;
use App\Http\Middleware\CoopMiddleware;
use App\Http\Middleware\ForceJsonResponseMiddleware;
use App\Http\Middleware\RejectIfAuthenticatedMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ForceJsonResponseMiddleware::class);
        $middleware->append(CoopMiddleware::class);
        $middleware->append(ActivityLogMiddleware::class);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'guest.api' => RejectIfAuthenticatedMiddleware::class,
            'modules' => \App\Http\Middleware\ModuleMiddleware::class,
            'permissions' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated',
                ], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint or Resource not found.',
                ], 404);
            }
        });
    })->create();
