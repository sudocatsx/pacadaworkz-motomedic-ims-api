<?php
namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ActivityLogService
{

public function log(
    string $module,
    string $action,
    string $description,
    ?int $userId = null // make it explicitly nullable
) {
    // Use provided userId or fallback to auth()->id()
    $userId = $userId ?? auth()->id();

    //  if still null, skip or throw exception to avoid DB error
    if (!$userId) {
        // Option 1: skip logging
        return;

        // Option 2: throw exception
        // throw new \Exception("Cannot log activity: user_id is null");
    }

    ActivityLog::create([
        'user_id'     => $userId,
        'module'      => $module,
        'action'      => $action,
        'description' => $description,
        'ip_address'  => request()->ip(),
        'user_agent'  => request()->userAgent(),
    ]);
}

    public function getLogs(array $filters, User $user, bool $canViewAll): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = min(max($perPage, 1), 100);

        return $this->filteredQuery($filters, $user, $canViewAll)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getExport(array $filters, User $user, bool $canViewAll): Collection
    {
        return $this->filteredQuery($filters, $user, $canViewAll)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getFilterOptions(User $user, bool $canViewAll): array
    {
        $baseQuery = $this->scopeQueryForUser(ActivityLog::query(), $user, $canViewAll);

        $modules = (clone $baseQuery)
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->values();

        $actions = (clone $baseQuery)
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values();

        $options = [
            'modules' => $modules,
            'actions' => $actions,
        ];

        if ($canViewAll) {
            $userIds = (clone $baseQuery)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

            $options['users'] = User::query()
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return $options;
    }

    private function filteredQuery(array $filters, User $user, bool $canViewAll): Builder
    {
        $query = $this->scopeQueryForUser(ActivityLog::with('user'), $user, $canViewAll);

        if ($canViewAll && !empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $like = "%{$search}%";

            $query->where(function (Builder $q) use ($like) {
                $q->whereRaw('LOWER(module) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(action) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$like])
                    ->orWhereHas('user', function (Builder $userQuery) use ($like) {
                        $userQuery->whereRaw('LOWER(name) LIKE ?', [$like]);
                    });
            });
        }

        $this->applyPeriodFilter($query, $filters);

        return $query;
    }

    private function scopeQueryForUser(Builder $query, User $user, bool $canViewAll): Builder
    {
        if (!$canViewAll) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function applyPeriodFilter(Builder $query, array $filters): void
    {
        $period = $filters['period'] ?? 'all';
        $timezone = config('app.timezone');
        $now = Carbon::now($timezone);

        if ($period === 'today') {
            $query->whereBetween('created_at', [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
            ]);
        }

        if ($period === 'last_7_days') {
            $query->whereBetween('created_at', [
                $now->copy()->subDays(6)->startOfDay(),
                $now->copy()->endOfDay(),
            ]);
        }

        if ($period === 'last_month') {
            $query->whereBetween('created_at', [
                $now->copy()->subMonthNoOverflow()->startOfDay(),
                $now->copy()->endOfDay(),
            ]);
        }

        if ($period === 'custom') {
            $query->whereBetween('created_at', [
                Carbon::parse($filters['start_date'], $timezone)->startOfDay(),
                Carbon::parse($filters['end_date'], $timezone)->endOfDay(),
            ]);
        }
    }
}
