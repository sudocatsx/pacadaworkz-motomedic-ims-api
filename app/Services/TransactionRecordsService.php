<?php

namespace App\Services;

use App\Models\SalesItem;
use App\Models\SalesTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TransactionRecordsService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['user', 'sales_items.product'])
            ->latest('created_at')
            ->paginate(min(max((int) ($filters['per_page'] ?? 10), 1), 100));
    }

    public function summary(array $filters): array
    {
        $query = $this->filteredQuery($filters);
        $transactions = (clone $query)->count();
        $revenue = (float) (clone $query)
            ->where('status', '!=', 'voided')
            ->selectRaw('COALESCE(SUM(total_amount - refund_amount), 0) as value')
            ->value('value');
        $revenueTransactions = (clone $query)->where('status', '!=', 'voided')->count();
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'transactions' => $transactions,
            'revenue' => $revenue,
            'average_sale' => $revenueTransactions > 0 ? $revenue / $revenueTransactions : 0,
            'status_counts' => collect(['completed', 'voided', 'refunded', 'partially_refunded'])
                ->mapWithKeys(fn (string $status) => [$status => (int) ($statusCounts[$status] ?? 0)]),
        ];
    }

    public function export(array $filters): Collection
    {
        return $this->filteredQuery($filters)
            ->with('user')
            ->latest('created_at')
            ->get();
    }

    public function dailyReport(string $date, ?int $userId = null): array
    {
        $timezone = config('app.timezone');
        $day = Carbon::createFromFormat('Y-m-d', $date, $timezone);
        $filters = [
            'period' => 'custom',
            'start_date' => $day->toDateString(),
            'end_date' => $day->toDateString(),
        ];
        if ($userId) {
            $filters['user_id'] = $userId;
        }
        $query = $this->filteredQuery($filters);
        $active = (clone $query)->where('status', '!=', 'voided');
        $grossSales = (float) (clone $active)->sum('subtotal');
        $netSales = (float) (clone $active)
            ->selectRaw('COALESCE(SUM(total_amount - refund_amount), 0) as value')
            ->value('value');

        $paymentBreakdown = (clone $active)
            ->selectRaw('payment_method, COUNT(*) as transaction_count, COALESCE(SUM(total_amount - refund_amount), 0) as total')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'payment_method' => $row->payment_method,
                'count' => (int) $row->transaction_count,
                'total' => (float) $row->total,
            ]);

        $transactionIds = (clone $active)->pluck('id');
        $products = SalesItem::query()
            ->join('products', 'products.id', '=', 'sales_items.product_id')
            ->whereIn('sales_items.sales_transactions_id', $transactionIds)
            ->selectRaw('products.id, products.name, sales_items.unit_price, SUM(sales_items.quantity - COALESCE(sales_items.quantity_returned, 0)) as quantity_sold')
            ->groupBy('products.id', 'products.name', 'sales_items.unit_price')
            ->havingRaw('SUM(sales_items.quantity - COALESCE(sales_items.quantity_returned, 0)) > 0')
            ->orderByDesc('quantity_sold')
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->id,
                'product_name' => $row->name,
                'quantity_sold' => (int) $row->quantity_sold,
                'product_price' => (float) $row->unit_price,
            ]);

        return [
            'date' => $day->toDateString(),
            'summary' => $this->summary($filters),
            'sales_overview' => ['gross_sales' => $grossSales, 'net_sales' => $netSales],
            'payment_breakdown' => $paymentBreakdown,
            'products_sold' => $products,
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = SalesTransaction::query();

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.mb_strtolower($filters['search']).'%';
            $query->where(function (Builder $nested) use ($like) {
                $nested->whereRaw('LOWER(transaction_no) LIKE ?', [$like])
                    ->orWhereHas('user', fn (Builder $user) => $user->whereRaw('LOWER(name) LIKE ?', [$like]));
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        $this->applyPeriod($query, $filters);

        return $query;
    }

    private function applyPeriod(Builder $query, array $filters): void
    {
        $period = $filters['period'] ?? 'today';
        $now = Carbon::now(config('app.timezone'));
        $range = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek()->startOfDay(), $now->copy()->endOfWeek()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom' => [
                Carbon::parse($filters['start_date'], config('app.timezone'))->startOfDay(),
                Carbon::parse($filters['end_date'], config('app.timezone'))->endOfDay(),
            ],
            default => null,
        };

        if ($range) {
            $query->whereBetween('created_at', $range);
        }
    }
}
