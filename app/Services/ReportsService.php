<?php

namespace App\Services;

use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ReportCSVService;

class ReportsService
{

    protected $reportCSVService;

    public function __construct(ReportCSVService $reportCSVService)
    {
        $this->reportCSVService = $reportCSVService;
    }

    private function normalizeDateRange($start = null, $end = null, ?string $fallbackTable = null): array
    {
        if (!$start && $fallbackTable) {
            $start = DB::table($fallbackTable)->min('created_at');
        }

        $start = $start
            ? Carbon::parse($start)->startOfDay()
            : Carbon::today()->startOfDay();

        $end = $end
            ? Carbon::parse($end)->endOfDay()
            : Carbon::today()->endOfDay();

        return [$start, $end];
    }

    // sales report
    public function getSalesReport($start = null, $end = null)
    {

        [$start, $end] = $this->normalizeDateRange($start, $end, 'sales_transactions');


        $query = SalesTransaction::query();
        $trend = SalesTransaction::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->when($start && $end, function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end]);
            })
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $staffNameExpression = "COALESCE(NULLIF(users.name, ''), NULLIF(TRIM(users.first_name || ' ' || users.last_name), ''), 'Unknown Staff')";
        $salesByStaff = DB::table('sales_transactions')
            ->leftJoin('users', 'sales_transactions.user_id', '=', 'users.id')
            ->whereBetween('sales_transactions.created_at', [$start, $end])
            ->selectRaw("{$staffNameExpression} as staff_name, SUM(sales_transactions.total_amount) as total")
            ->groupByRaw($staffNameExpression)
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn ($staff) => [
                $staff->staff_name => (float) $staff->total,
            ]);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }


        return [
            'total_sales' => $query->sum('total_amount'),
            'transactions' => $query->count(),
            'average_transaction' => round($query->avg('total_amount'), 2),
            'trend' => $trend,
            'sales_by_staff' => $salesByStaff,
        ];
    }


    // purchase report
    public function getPurchases($start = null, $end = null)
    {
        [$start, $end] = $this->normalizeDateRange($start, $end, 'purchase_orders');

        //  $itemsQuery = PurchaseItem::query(); baka magamit soon
        $ordersQuery = PurchaseOrder::query();
        $ordersQuery->whereBetween('created_at', [$start, $end]);
        $averageOrder = round($ordersQuery->avg('total_amount'), 2) ?? 0;

        $trend = PurchaseOrder::selectRaw('DATE(created_at) as date, SUM(total_amount)as total')
            ->when($start && $end, function ($x) use ($start, $end) {
                $x->whereBetween('created_at', [$start, $end]);
            })
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'total_purchases' => $ordersQuery->sum('total_amount'),
            'purchase_orders' => $ordersQuery->count(),
            'average_orders' => $averageOrder,
            'trend' => $trend
        ];
    }

    //inventory report
    public function getInventory($start = null, $end = null)
    {
        $start = $start ?? Carbon::now()->format('Y-m-d');
        $end = $end ?? Carbon::now()->format('Y-m-d');
        // query of product
        $productsQuery = Product::query();
        //inventory
        $inventory = DB::table('inventory');
        // inventory value
        $totalInventoryValue = $inventory
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->select(DB::raw('SUM(products.cost_price * inventory.quantity) as total_value'))
            ->value('total_value');

        // low stock
        $lowStock = DB::table('inventory')
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->whereNull('inventory.deleted_at')
            ->whereColumn('inventory.quantity', '<=', 'products.reorder_level')
            ->where('inventory.quantity', '>', 0)
            ->count();
        // no stock
        $noStock =  DB::table('inventory')
            ->whereNull('deleted_at')
            ->where('quantity', '=', 0)
            ->count();

        //product distribution by category
        $distCategory = DB::table('categories as a')
            ->join('products as b', 'b.category_id', '=', 'a.id')
            ->select('a.name', DB::raw('COUNT(b.category_id) as total'))
            ->groupBy('a.name')
            ->get();
        // inventory value by category
        $valCategory = DB::table('categories as a')
            ->join('products as b', 'b.category_id', '=', 'a.id')
            ->join('inventory as c', 'c.product_id', '=', 'b.id')
            ->select('a.name', DB::raw('SUM(c.quantity * b.unit_price) as inventory_value'))
            ->groupBy('a.name')
            ->get();
        return [
            'total_products' => $productsQuery->count(),
            'total_value' => $totalInventoryValue,
            'low_stock' => $lowStock,
            'out_of_stock' => $noStock,
            'ditribution_category' => $distCategory,
            'inventory_value_category' => $valCategory
        ];
    }


    // get Performance

    public function getPerformance($start = null, $end = null)
    {
        [$start, $end] = $this->normalizeDateRange($start, $end, 'sales_items');

        $revenueByCategory = DB::table('categories as a')
            ->leftJoin('products as b', 'a.id', '=', 'b.category_id')
            ->leftJoin('sales_items as c', function ($join) use ($start, $end) {
                $join->on('b.id', '=', 'c.product_id')
                    ->whereBetween('c.created_at', [$start, $end]);
            })->select(
                'a.name',
                DB::raw('COALESCE(SUM(c.unit_price * c.quantity), 0) as total')
            )
            ->groupBy('a.name')
            ->orderBy('a.name')
            ->get();


        $revenueByBrand = DB::table('brands as a')
            ->leftJoin('products as b', 'a.id', '=', 'b.brand_id')
            ->leftJoin('sales_items as c', function ($join) use ($start, $end) {
                $join->on('b.id', '=', 'c.product_id')
                    ->whereBetween('c.created_at', [$start, $end]);
            })->select(
                'a.name',
                DB::raw('COALESCE(SUM(c.unit_price * c.quantity), 0) as total')
            )
            ->groupBy('a.name')
            ->orderBy('a.name')
            ->get();

        return [
            'revenue_by_category' => $revenueByCategory,
            'revenue_by_brand' => $revenueByBrand
        ];
    }


    public function getStockAdjustments($start = null, $end = null)
    {
        [$start, $end] = $this->normalizeDateRange($start, $end, 'stock_adjustments');

        // Total adjustments count
        $totalAdjustments = DB::table('stock_adjustments')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // Adjustment value
        $adjustmentValue = DB::table('stock_adjustments as a')
            ->join('stock_movements as b', 'a.id', '=', 'b.reference_id')
            ->join('products as c', 'c.id', '=', 'b.product_id')
            ->where('b.reference_type', 'adjustment')
            ->whereBetween('a.created_at', [$start, $end])
            ->select(DB::raw("
            SUM(
                CASE
                    WHEN b.movement_type = 'in' THEN b.quantity * c.cost_price
                    WHEN b.movement_type = 'out' THEN -b.quantity * c.cost_price
                END
            ) as adjustment_value
        "))
            ->value('adjustment_value') ?? 0;

        // Adjustments by reason
        $reasonCounts = DB::table('stock_adjustments')
            ->whereBetween('created_at', [$start, $end])
            ->select('reason', DB::raw('COUNT(*) as num_reasons'))
            ->groupBy('reason')
            ->get();

        return [
            'total_adjustments'       => $totalAdjustments,
            'adjustments_value'       => $adjustmentValue,
            'adjustments_by_reason'   => $reasonCounts
        ];
    }


    public function getProfitLossReport($start = null, $end = null)
    {
        [$start, $end] = $this->normalizeDateRange($start, $end, 'stock_movements');

        // Revenue
        $revenue = SalesTransaction::whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        // Cost of goods sold
        $costOfGoods = DB::table('sales_items as a')
            ->join('products as b', 'b.id', '=', 'a.product_id')
            ->whereBetween('a.created_at', [$start, $end])
            ->select(DB::raw('SUM(a.quantity * b.cost_price) as cost_of_goods'))
            ->value('cost_of_goods') ?? 0;

        // Gross profit
        $grossProfit = $revenue - $costOfGoods;



        $adjustmentLoss = DB::table('stock_adjustments as a')
            ->join('stock_movements as b', 'a.id', '=', 'b.reference_id')
            ->join('products as c', 'c.id', '=', 'b.product_id')->where('reference_type', 'adjustment')
            ->where('movement_type', 'out')->whereBetween('a.created_at', [$start, $end])->select(DB::raw('SUM(b.quantity * c.cost_price) as total_cost'))
            ->value('total_cost') ?? 0;

        // Net profit
        $netProfit = $grossProfit - $adjustmentLoss;

        // Profit margin (%)
        $profitMargin = $revenue > 0
            ? round(($netProfit / $revenue) * 100, 2)
            : 0;

        return [

            'revenue'         => $revenue,
            'cost_of_goods'   => $costOfGoods,
            'gross_profit'    => $grossProfit,
            'adjustment_loss' => $adjustmentLoss,
            'net_profit'      => $netProfit,
            'profit_margin'   => $profitMargin,
        ];
    }


    public function getReportCSV($start, $end, $type)
    {
        switch ($type) {
            case 'sales':
                $data = $this->getSalesReport($start, $end);
                return $this->reportCSVService->exportSales($data);
            case 'purchase':
                $data = $this->getPurchases($start, $end);
                return $this->reportCSVService->exportPurchase($data);
            case 'inventory':
                $data = $this->getInventory($start, $end);
                return $this->reportCSVService->exportInventory($data);
            case 'performance':
                $data = $this->getPerformance($start, $end);
                return $this->reportCSVService->exportPerformance($data);
            case 'adjustments':
                $data = $this->getStockAdjustments($start, $end);
                return $this->reportCSVService->exportAdjustments($data);
            case 'profitloss':
                $data = $this->getProfitLossReport($start, $end);
                return $this->reportCSVService->exportProfitAndLoss($data);
        }
    }
}
