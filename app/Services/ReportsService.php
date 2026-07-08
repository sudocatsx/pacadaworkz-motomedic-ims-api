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

        $purchaseBySupplier = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->whereBetween('purchase_orders.created_at', [$start, $end])
            ->selectRaw("COALESCE(suppliers.name, 'Unknown Supplier') as supplier_name, SUM(purchase_orders.total_amount) as total")
            ->groupByRaw("COALESCE(suppliers.name, 'Unknown Supplier')")
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn ($supplier) => [
                $supplier->supplier_name => (float) $supplier->total,
            ]);

        return [
            'total_purchases' => $ordersQuery->sum('total_amount'),
            'purchase_orders' => $ordersQuery->count(),
            'average_orders' => $averageOrder,
            'trend' => $trend,
            'purchase_by_supplier' => $purchaseBySupplier,
        ];
    }

    //inventory report
    public function getInventory($start = null, $end = null)
    {
        [$start, $end] = $this->normalizeDateRange($start, $end, 'stock_movements');

        // query of product
        $productsQuery = Product::query();
        //inventory
        $inventory = DB::table('inventory');
        // inventory value
        $totalInventoryValue = $inventory
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->whereNull('inventory.deleted_at')
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

        $lowStockItems = DB::table('inventory')
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->whereNull('inventory.deleted_at')
            ->whereColumn('inventory.quantity', '<=', 'products.reorder_level')
            ->where('inventory.quantity', '>', 0)
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'inventory.quantity as current_stock',
                'products.reorder_level as reorder_point'
            )
            ->orderBy('inventory.quantity')
            ->orderBy('products.name')
            ->get();

        // no stock
        $noStock =  DB::table('inventory')
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->whereNull('inventory.deleted_at')
            ->where('inventory.quantity', '=', 0)
            ->count();

        $outOfStockItems = DB::table('inventory')
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->whereNull('inventory.deleted_at')
            ->where('inventory.quantity', '<=', 0)
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'products.reorder_level as reorder_point'
            )
            ->orderBy('products.name')
            ->get();

        //product distribution by category
        $distCategory = DB::table('categories as a')
            ->join('products as b', 'b.category_id', '=', 'a.id')
            ->whereNull('a.deleted_at')
            ->whereNull('b.deleted_at')
            ->select('a.name', DB::raw('COUNT(b.category_id) as total'))
            ->groupBy('a.name')
            ->orderBy('a.name')
            ->get();
        // inventory value by category
        $valCategory = DB::table('categories as a')
            ->join('products as b', 'b.category_id', '=', 'a.id')
            ->join('inventory as c', 'c.product_id', '=', 'b.id')
            ->whereNull('a.deleted_at')
            ->whereNull('b.deleted_at')
            ->whereNull('c.deleted_at')
            ->select('a.name', DB::raw('SUM(c.quantity * b.cost_price) as inventory_value'))
            ->groupBy('a.name')
            ->orderBy('a.name')
            ->get();

        $movementSummary = DB::table('stock_movements')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END), 0) as stock_in_quantity,
                COALESCE(SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END), 0) as stock_out_quantity,
                COUNT(*) as movement_count
            ")
            ->first();

        $movementBySource = DB::table('stock_movements')
            ->whereBetween('created_at', [$start, $end])
            ->select(
                'reference_type',
                DB::raw('SUM(quantity) as quantity'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('reference_type')
            ->orderBy('reference_type')
            ->get()
            ->mapWithKeys(fn ($source) => [
                $source->reference_type => [
                    'quantity' => (int) $source->quantity,
                    'count' => (int) $source->count,
                ],
            ]);

        $topMovedProducts = DB::table('stock_movements as sm')
            ->join('products as p', 'sm.product_id', '=', 'p.id')
            ->whereNull('p.deleted_at')
            ->whereBetween('sm.created_at', [$start, $end])
            ->select(
                'p.id',
                'p.name',
                'p.sku',
                DB::raw("SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as stock_in"),
                DB::raw("SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as stock_out"),
                DB::raw('SUM(sm.quantity) as total_moved')
            )
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->orderByDesc('total_moved')
            ->limit(10)
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock_in' => (int) $product->stock_in,
                'stock_out' => (int) $product->stock_out,
                'net_change' => (int) $product->stock_in - (int) $product->stock_out,
                'total_moved' => (int) $product->total_moved,
            ]);

        $userNameExpression = "COALESCE(NULLIF(users.name, ''), NULLIF(TRIM(users.first_name || ' ' || users.last_name), ''), 'System')";
        $recentMovements = DB::table('stock_movements as sm')
            ->join('products as p', 'sm.product_id', '=', 'p.id')
            ->leftJoin('users', 'sm.user_id', '=', 'users.id')
            ->whereNull('p.deleted_at')
            ->whereBetween('sm.created_at', [$start, $end])
            ->selectRaw("
                sm.id,
                sm.created_at,
                p.name as product_name,
                p.sku,
                sm.movement_type,
                sm.quantity,
                sm.reference_type,
                {$userNameExpression} as user_name
            ")
            ->orderByDesc('sm.created_at')
            ->orderByDesc('sm.id')
            ->limit(20)
            ->get();

        $stockInQuantity = (int) ($movementSummary->stock_in_quantity ?? 0);
        $stockOutQuantity = (int) ($movementSummary->stock_out_quantity ?? 0);

        return [
            'total_products' => $productsQuery->count(),
            'total_value' => $totalInventoryValue,
            'low_stock' => $lowStock,
            'out_of_stock' => $noStock,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'ditribution_category' => $distCategory,
            'distribution_category' => $distCategory,
            'inventory_value_category' => $valCategory,
            'movement_summary' => [
                'stock_in_quantity' => $stockInQuantity,
                'stock_out_quantity' => $stockOutQuantity,
                'net_stock_change' => $stockInQuantity - $stockOutQuantity,
                'movement_count' => (int) ($movementSummary->movement_count ?? 0),
            ],
            'movement_by_source' => $movementBySource,
            'top_moved_products' => $topMovedProducts,
            'recent_movements' => $recentMovements,
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
