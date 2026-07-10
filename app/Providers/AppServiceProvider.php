<?php

namespace App\Providers;

use App\Http\Middleware\ModuleMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //

        Route::aliasMiddleware('role', RoleMiddleware::class);
        Route::aliasMiddleware('modules', ModuleMiddleware::class);
        Route::aliasMiddleware('permissions', PermissionMiddleware::class);
    }
}
