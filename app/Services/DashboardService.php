<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(private readonly FinancialAggregationService $financials) {}

    // get dashboard stats
    public function getStats()
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        $lowstock = Inventory::join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->whereColumn('inventory.quantity', '<=', 'products.reorder_level')
            ->where('inventory.quantity', '>', 0)
            ->count();

        $outOfStock = Inventory::join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->where('inventory.quantity', 0)
            ->count();

        $user = auth('api')->user();
        $stats = [
            'my_transactions_today' => SalesTransaction::where('user_id', $user->id)
                ->where('status', '!=', 'voided')
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->count(),
            'my_items_sold_today' => (int) SalesItem::join('sales_transactions', 'sales_items.sales_transactions_id', '=', 'sales_transactions.id')
                ->where('sales_transactions.user_id', $user->id)
                ->where('sales_transactions.status', '!=', 'voided')
                ->whereBetween('sales_transactions.created_at', [$todayStart, $todayEnd])
                ->sum(DB::raw('sales_items.quantity - sales_items.quantity_returned')),
            'low_stock' => $lowstock,
            'out_of_stock' => $outOfStock,
        ];

        if (! $this->userHasPermission($user, 'Dashboard', 'View Financial Data')) {
            return $stats;
        }

        $todaysSales = $this->financials->netSales($todayStart, $todayEnd);
        $todaysCostOfGoodsSold = $this->financials->cogs($todayStart, $todayEnd);
        $todaysStockAdjustmentLosses = $this->financials->adjustmentLosses($todayStart, $todayEnd);

        return array_merge($stats, [
            'todays_sales' => $todaysSales,
            'todays_net_sales' => $todaysSales,
            'todays_transactions' => SalesTransaction::where('status', '!=', 'voided')->whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'todays_items_sold' => (int) SalesItem::join('sales_transactions', 'sales_items.sales_transactions_id', '=', 'sales_transactions.id')
                ->where('sales_transactions.status', '!=', 'voided')
                ->whereBetween('sales_transactions.created_at', [$todayStart, $todayEnd])
                ->sum(DB::raw('sales_items.quantity - sales_items.quantity_returned')),
            'todays_purchases' => (float) DB::table('purchase_orders')
                ->whereDate('order_date', Carbon::today()->toDateString())
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount'),
            'todays_cost_of_goods_sold' => $todaysCostOfGoodsSold,
            'todays_stock_adjustment_losses' => $todaysStockAdjustmentLosses,
            'todays_gross_profit' => $todaysSales - $todaysCostOfGoodsSold,
            'total_products' => Product::count(),
            'total_revenue' => (float) SalesTransaction::sum(DB::raw(FinancialAggregationService::NET_SALES)),
            'total_transactions' => SalesTransaction::where('status', '!=', 'voided')->count(),
            'total_sales' => (int) SalesItem::join('sales_transactions', 'sales_items.sales_transactions_id', '=', 'sales_transactions.id')
                ->where('sales_transactions.status', '!=', 'voided')
                ->sum(DB::raw('sales_items.quantity - sales_items.quantity_returned')),
            'active_users' => User::count(),
        ]);
    }

    // get sales trend
    public function getSalesTrend()
    {
        $sales = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dateConvert = Carbon::now()->subDays($i)->format('M d');
            $total = SalesTransaction::whereDate('created_at', $date)
                ->sum(DB::raw(FinancialAggregationService::NET_SALES));

            $sales[$dateConvert] = $total;
        }

        return $sales;
    }

    // get top products
    public function getTopProducts()
    {
        $start = Carbon::now()->subDays(6)->startOfDay();
        $end = Carbon::now()->endOfDay();

        return Product::query()
            ->leftJoin('sales_items', 'products.id', '=', 'sales_items.product_id')
            ->leftJoin('sales_transactions', function ($join) use ($start, $end) {
                $join->on('sales_items.sales_transactions_id', '=', 'sales_transactions.id')
                    ->where('sales_transactions.status', '!=', 'voided')
                    ->whereBetween('sales_transactions.created_at', [$start, $end]);
            })
            ->whereNull('products.deleted_at')
            ->select(
                'products.name',
                DB::raw('COALESCE(SUM(CASE WHEN sales_transactions.id IS NOT NULL THEN sales_items.quantity - sales_items.quantity_returned ELSE 0 END), 0) as total_sold')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get()
            ->pluck('total_sold', 'name');
    }

    // get revenue by category
    public function getRevenueByCategory()
    {
        return Category::query()
            ->select(
                'categories.name as category_name',
                DB::raw("COALESCE(SUM(CASE WHEN sales_transactions.id IS NOT NULL THEN COALESCE(NULLIF(sales_items.net_line_total, 0), sales_items.unit_price * (sales_items.quantity - COALESCE(sales_items.quantity_returned, 0))) - COALESCE(sales_items.refunded_line_amount, 0) ELSE 0 END), 0) as total_revenue")
            )
            ->leftJoin('products', 'categories.id', '=', 'products.category_id')
            ->leftJoin('sales_items', 'products.id', '=', 'sales_items.product_id')
            ->leftJoin('sales_transactions', function ($join) {
                $join->on('sales_items.sales_transactions_id', '=', 'sales_transactions.id')
                    ->where('sales_transactions.status', '!=', 'voided');
            })
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();
    }

    // get revenue by brand
    public function getRevenueByBrand()
    {
        return Brand::query()
            ->select(
                'brands.name as brand_name',
                DB::raw("COALESCE(SUM(CASE WHEN sales_transactions.id IS NOT NULL THEN COALESCE(NULLIF(sales_items.net_line_total, 0), sales_items.unit_price * (sales_items.quantity - COALESCE(sales_items.quantity_returned, 0))) - COALESCE(sales_items.refunded_line_amount, 0) ELSE 0 END), 0) as total_revenue")
            )
            ->leftJoin('products', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('sales_items', 'products.id', '=', 'sales_items.product_id')
            ->leftJoin('sales_transactions', function ($join) {
                $join->on('sales_items.sales_transactions_id', '=', 'sales_transactions.id')
                    ->where('sales_transactions.status', '!=', 'voided');
            })
            ->groupBy('brands.id', 'brands.name')
            ->orderByDesc('total_revenue')
            ->get();
    }

    // get inventory Overview
    public function getInventoryOverview()
    {
        $totalInventoryValue = Inventory::join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->where('inventory.quantity', '>', 0)
            ->sum(DB::raw('inventory.quantity * products.cost_price'));

        $totalInStocksProducts = Inventory::join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->where('inventory.quantity', '>', 0)
            ->count();

        $reOrderStock = Inventory::join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->whereColumn('inventory.quantity', '<=', 'products.reorder_level')
            ->where('inventory.quantity', '>=', 0)
            ->count();

        return [
            'total_inventory_value' => floatval($totalInventoryValue),
            'in_stock_products' => $totalInStocksProducts,
            'need_reorder' => $reOrderStock,
        ];
    }

    // get recent activities
    public function getRecentActivities()
    {
        $user = auth('api')->user();

        $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

        if (! $this->userHasPermission($user, 'Activity Logs', 'View All')) {
            $query->where('user_id', $user->id);
        }

        return $query->take(10)->get();
    }

    private function userHasPermission(User $user, string $module, string $name): bool
    {
        $user->loadMissing('role.permissions');

        return $user->role?->permissions->contains(
            fn ($permission) => $permission->module === $module && $permission->name === $name
        ) ?? false;
    }
}
