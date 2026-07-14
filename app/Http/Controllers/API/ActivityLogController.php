<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\ActivityLogResource;
use App\Services\ActivityLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    protected $logservice;

    public function __construct(ActivityLogService $logservice)
    {
        $this->logservice = $logservice;
    }

    // show logs
    public function showLogs(Request $request)
    {
        $user = $request->user('api');
        $canViewAll = $this->hasActivityLogsPermission($user, 'View All');
        $canViewOwn = $this->hasActivityLogsPermission($user, 'View Own');

        if (! $canViewAll && ! $canViewOwn) {
            throw new AuthorizationException;
        }

        $filters = $this->validatedFilters($request);
        $result = $this->logservice->getLogs($filters, $user, $canViewAll);
        $filterOptions = $this->logservice->getFilterOptions($user, $canViewAll);

        return response()->json([
            'success' => true,
            'data' => ActivityLogResource::collection($result),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
                'total_pages' => $result->lastPage(),
                'filter_options' => $filterOptions,
                'permissions' => [
                    'view_all' => $canViewAll,
                    'view_own' => $canViewOwn,
                    'export' => $this->hasActivityLogsPermission($user, 'Export'),
                ],
            ],
        ]);
    }

    // export activity logs
    public function export(Request $request)
    {
        $user = $request->user('api');
        $canViewAll = $this->hasActivityLogsPermission($user, 'View All');
        $canViewOwn = $this->hasActivityLogsPermission($user, 'View Own');

        if (! $this->hasActivityLogsPermission($user, 'Export') || (! $canViewAll && ! $canViewOwn)) {
            throw new AuthorizationException;
        }

        $filters = $this->validatedFilters($request);
        $logs = $this->logservice->getExport($filters, $user, $canViewAll);

        $response = new StreamedResponse(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Timestamp', 'User', 'Module', 'Action', 'Details']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at,
                    optional($log->user)->name,
                    $log->module,
                    $log->action,
                    $log->description,
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="activity-logs.csv"'
        );

        return $response;
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'period' => ['nullable', 'string', 'in:all,today,last_7_days,last_month,custom'],
            'start_date' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    private function hasActivityLogsPermission($user, string $permissionName): bool
    {
        if (! $user || ! $user->role) {
            return false;
        }

        if (! $user->role->relationLoaded('permissions')) {
            $user->role->load('permissions');
        }

        return $user->role->permissions->contains(function ($permission) use ($permissionName) {
            return $permission->module === 'Activity Logs' && $permission->name === $permissionName;
        });
    }
}
