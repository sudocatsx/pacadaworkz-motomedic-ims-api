<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Models\SalesItem;
use App\Models\Inventory;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    // get dashboard stats
    public function getStats()
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        $userCount = User::count();
        $productCount = Product::count();
        $transactionCount = SalesTransaction::where('status', '!=', 'voided')->count();

        $salesItem = SalesItem::join('sales_transactions', 'sales_items.sales_transactions_id', '=', 'sales_transactions.id')
            ->where('sales_transactions.status', '!=', 'voided')
            ->sum(DB::raw('sales_items.quantity - sales_items.quantity_returned'));

        $revenue = (float) SalesTransaction::where('status', '!=', 'voided')
            ->sum(DB::raw('subtotal - refund_amount'));

        $todaysSales = (float) SalesTransaction::where('status', '!=', 'voided')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum(DB::raw('total_amount - refund_amount'));

        $todaysTransactions = SalesTransaction::where('status', '!=', 'voided')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $todaysItemsSold = (int) SalesItem::join('sales_transactions', 'sales_items.sales_transactions_id', '=', 'sales_transactions.id')
            ->where('sales_transactions.status', '!=', 'voided')
            ->whereBetween('sales_transactions.created_at', [$todayStart, $todayEnd])
            ->sum(DB::raw('sales_items.quantity - sales_items.quantity_returned'));

        $todaysPurchases = (float) DB::table('purchase_orders')
            ->whereDate('order_date', Carbon::today()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $todaysCostOfGoodsSold = (float) DB::table('sales_items')
            ->join('sales_transactions', 'sales_items.sales_transactions_id', '=', 'sales_transactions.id')
            ->join('products', 'products.id', '=', 'sales_items.product_id')
            ->where('sales_transactions.status', '!=', 'voided')
            ->whereBetween('sales_transactions.created_at', [$todayStart, $todayEnd])
            ->sum(DB::raw('(sales_items.quantity - sales_items.quantity_returned) * products.cost_price'));

        $todaysStockAdjustmentLosses = (float) DB::table('stock_adjustments')
            ->join('stock_movements', 'stock_adjustments.id', '=', 'stock_movements.reference_id')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->where('stock_movements.reference_type', 'adjustment')
            ->where('stock_movements.movement_type', 'out')
            ->whereBetween('stock_adjustments.created_at', [$todayStart, $todayEnd])
            ->sum(DB::raw('stock_movements.quantity * products.cost_price'));

        $todaysNetProfit = $todaysSales - $todaysCostOfGoodsSold - $todaysStockAdjustmentLosses;

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
        $dailyStats = [
            'todays_sales' => $todaysSales,
            'todays_transactions' => $todaysTransactions,
            'todays_items_sold' => $todaysItemsSold,
            'todays_purchases' => $todaysPurchases,
            'todays_cost_of_goods_sold' => $todaysCostOfGoodsSold,
            'todays_stock_adjustment_losses' => $todaysStockAdjustmentLosses,
            'todays_net_profit' => $todaysNetProfit,
            'low_stock' => $lowstock,
            'out_of_stock' => $outOfStock,
        ];

        if ($user->role->role_name == 'admin' || $user->role->role_name == 'superadmin') {
            return array_merge($dailyStats, [
                'total_products' => $productCount,
                'total_revenue' => $revenue,
                'total_transactions' => $transactionCount,
                'total_sales' => $salesItem,
                'active_users' => $userCount
            ]);
        } else if ($user->role->role_name == 'staff') {
            return array_merge($dailyStats, [
                'total_products' => $productCount,
            ]);
        }

        return $dailyStats;
    }

    // get sales trend
    public function getSalesTrend()
    {
        $sales = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dateConvert = Carbon::now()->subDays($i)->format('M d');
            $total = SalesTransaction::whereDate('created_at', $date)->sum('subtotal');

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
                DB::raw('COALESCE(SUM(CASE WHEN sales_transactions.id IS NOT NULL THEN sales_items.unit_price * (sales_items.quantity - sales_items.quantity_returned) ELSE 0 END), 0) as total_revenue')
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
                DB::raw('COALESCE(SUM(CASE WHEN sales_transactions.id IS NOT NULL THEN sales_items.unit_price * (sales_items.quantity - sales_items.quantity_returned) ELSE 0 END), 0) as total_revenue')
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

        $totalInStocksProducts =  Inventory::join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->where('inventory.quantity', '>', 0)
            ->count();

        $reOrderStock =  Inventory::join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->whereColumn('inventory.quantity', '<=', 'products.reorder_level')
            ->where('inventory.quantity', '>=', 0)
            ->count();

        return [
            'total_inventory_value' => doubleval($totalInventoryValue),
            'in_stock_products' => $totalInStocksProducts,
            'need_reorder' => $reOrderStock
        ];
    }

    // get recent activities
    public function getRecentActivities()
    {
        $user = auth('api')->user();

        // Check if user is admin/superadmin OR has "View All Activity Logs" permission
        if (!$user->relationLoaded('role')) {
            $user->load('role.permissions');
        } elseif (!$user->role->relationLoaded('permissions')) {
            $user->role->load('permissions');
        }

        $isAdminOrSuperAdmin = in_array(strtolower($user->role->role_name), ['admin', 'superadmin']);

        $hasViewAllPermission = $user->role->permissions->contains(function ($permission) {
            return $permission->module === 'Activity Logs' && $permission->name === 'View All';
        });

        $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

        if (!$isAdminOrSuperAdmin && !$hasViewAllPermission) {
            // If not admin/superadmin and doesn't have "View All" permission, restrict to own logs
            $query->where('user_id', $user->id);
        }

        return $query->take(10)->get();
    }
}
