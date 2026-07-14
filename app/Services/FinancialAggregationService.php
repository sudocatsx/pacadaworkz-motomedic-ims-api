<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinancialAggregationService
{
    public const GROSS_SALES = "CASE WHEN status = 'voided' THEN 0 ELSE subtotal END";
    public const DISCOUNTS = "CASE WHEN status = 'voided' THEN 0 ELSE COALESCE(discount, 0) END";
    public const REFUNDS = "CASE WHEN status = 'voided' THEN 0 ELSE COALESCE(refund_amount, 0) END";
    public const NET_SALES = "CASE WHEN status = 'voided' THEN 0 ELSE total_amount - COALESCE(refund_amount, 0) END";
    public const ITEM_REVENUE = "CASE WHEN sales_transactions.status = 'voided' THEN 0 ELSE COALESCE(NULLIF(sales_items.net_line_total, 0), sales_items.unit_price * (sales_items.quantity - COALESCE(sales_items.quantity_returned, 0))) - COALESCE(sales_items.refunded_line_amount, 0) END";
    public const RETAINED_UNITS = "CASE WHEN sales_transactions.status = 'voided' THEN 0 WHEN sales_items.quantity > COALESCE(sales_items.quantity_returned, 0) THEN sales_items.quantity - COALESCE(sales_items.quantity_returned, 0) ELSE 0 END";
    public const COGS = "CASE WHEN sales_transactions.status = 'voided' THEN 0 WHEN sales_items.quantity > COALESCE(sales_items.quantity_returned, 0) THEN (sales_items.quantity - COALESCE(sales_items.quantity_returned, 0)) * COALESCE(NULLIF(sales_items.unit_cost, 0), products.cost_price) ELSE 0 END";

    public function transactions(CarbonInterface $start, CarbonInterface $end): Builder
    {
        return DB::table('sales_transactions')->whereBetween('created_at', [$start, $end]);
    }

    public function items(CarbonInterface $start, CarbonInterface $end): Builder
    {
        return DB::table('sales_items')
            ->join('sales_transactions', 'sales_items.sales_transactions_id', '=', 'sales_transactions.id')
            ->join('products', 'sales_items.product_id', '=', 'products.id')
            ->whereBetween('sales_transactions.created_at', [$start, $end]);
    }

    public function netSales(CarbonInterface $start, CarbonInterface $end): float
    {
        return (float) $this->transactions($start, $end)->sum(DB::raw(self::NET_SALES));
    }

    public function salesBridge(CarbonInterface $start, CarbonInterface $end): array
    {
        $totals = $this->transactions($start, $end)
            ->selectRaw('COALESCE(SUM('.self::GROSS_SALES.'), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM('.self::DISCOUNTS.'), 0) as discounts')
            ->selectRaw('COALESCE(SUM('.self::REFUNDS.'), 0) as refunds')
            ->selectRaw('COALESCE(SUM('.self::NET_SALES.'), 0) as net_sales')
            ->first();

        return [
            'gross_sales' => (float) $totals->gross_sales,
            'discounts' => (float) $totals->discounts,
            'refunds' => (float) $totals->refunds,
            'net_sales' => (float) $totals->net_sales,
        ];
    }

    public function cogs(CarbonInterface $start, CarbonInterface $end): float
    {
        return (float) $this->items($start, $end)->sum(DB::raw(self::COGS));
    }

    public function adjustmentLosses(CarbonInterface $start, CarbonInterface $end): float
    {
        return (float) DB::table('stock_adjustments as a')
            ->join('stock_movements as m', fn ($join) => $join->on('a.id', '=', 'm.reference_id')->where('m.reference_type', 'adjustment'))
            ->join('products as p', 'm.product_id', '=', 'p.id')
            ->where('m.movement_type', 'out')
            ->whereBetween('a.created_at', [$start, $end])
            ->sum(DB::raw('m.quantity * COALESCE(NULLIF(a.unit_cost, 0), p.cost_price)'));
    }
}
