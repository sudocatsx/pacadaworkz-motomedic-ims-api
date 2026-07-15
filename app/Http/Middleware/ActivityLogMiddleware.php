<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Execute controller first
        $response = $next($request);

        // Excluded paths (handled manually)
        $excludedPaths = [
            'api/v1/auth/login',
            'api/v1/auth/logout',
            'api/v1/pos/checkout',
            'api/v1/roles',
            'api/v1/tutorials*',

        ];

        if ($request->is($excludedPaths)) {
            return $response;
        }

        $segments = $request->segments();
        $lastSegment = end($segments);

        if ($request->isMethod('GET') && $lastSegment !== 'export') {
            return $response;
        }

        // Only log authenticated users
        if (! auth()->check()) {
            return $response;
        }

        $method = $request->method();
        $path = $request->path();

        $module = $this->detectModule($path);
        $action = $this->mapAction($method);

        $description = $this->buildDescription($request, $module);

        if ($description !== false) {
            app(ActivityLogService::class)->log(
                module: $module,
                action: $action,
                description: $description,
                userId: auth()->id()
            );
        }

        return $response;
    }

    /* =========================
     * ACTION MAPPING
     * ========================= */
    private function mapAction(string $method): string
    {
        return match ($method) {
            'GET' => 'View',
            'POST' => 'Create',
            'PUT',
            'PATCH' => 'Edit',
            'DELETE' => 'Delete',
            default => 'Performed',
        };
    }

    /* =========================
     * MODULE DETECTION
     * ========================= */
    private function detectModule(string $path): string
    {
        return match (true) {

            // Authentication
            str_starts_with($path, 'api/v1/auth') => 'Authentication',

            // Users & Access Control
            str_starts_with($path, 'api/v1/users') => 'Users',
            str_starts_with($path, 'api/v1/roles') => 'Roles',
            str_starts_with($path, 'api/v1/permissions') => 'Permissions',

            // Master Data
            str_starts_with($path, 'api/v1/categories') => 'Categories',
            str_starts_with($path, 'api/v1/brands') => 'Brands',
            str_starts_with($path, 'api/v1/attributes') => 'Attributes',
            str_starts_with($path, 'api/v1/products') => 'Products',
            str_starts_with($path, 'api/v1/suppliers') => 'Suppliers',

            // Inventory
            str_starts_with($path, 'api/v1/inventory') => 'Inventory',
            str_starts_with($path, 'api/v1/stock-movements') => 'Stock Movements',
            str_starts_with($path, 'api/v1/stock-adjustments') => 'Stock Adjustments',

            // POS
            str_starts_with($path, 'api/v1/pos') => 'POS',

            // Transactions
            str_starts_with($path, 'api/v1/purchases') => 'Purchases',
            str_starts_with($path, 'api/v1/sales') => 'Sales',

            // Reports
            str_starts_with($path, 'api/v1/reports') => 'Reports',
            str_starts_with($path, 'api/v1/dashboard') => 'Dashboard',
            str_starts_with($path, 'api/v1/activity-logs') => 'Activity Logs',

            default => 'General',
        };
    }

    /* =========================
     * DESCRIPTION BUILDER
     * ========================= */
    private function buildDescription(Request $request, string $module): string|false
    {
        $segments = $request->segments();
        $last = end($segments);

        if ($request->isMethod('GET')) {
            if ($last === 'export') {
                return "Export {$module}";
            }

            return false;
        }

        return false;
    }
}
