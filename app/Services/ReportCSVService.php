<?php

namespace App\Services;

class ReportCSVService
{
    public function exportSales($data)
    {
        return $this->arrayToCsv($this->salesRows($data));
    }

    public function salesRows($data): array
    {
        $csvData = [];
        $csvData[] = ['Metric', 'Value'];
        $csvData[] = ['Total Sales', $data['total_sales']];
        $csvData[] = ['Transactions', $data['transactions']];
        $csvData[] = ['Average Transaction', $data['average_transaction']];
        $csvData[] = []; // empty line
        $csvData[] = ['Sales Trend'];
        $csvData[] = ['Date', 'Total'];
        foreach ($data['trend'] as $trend) {
            $csvData[] = [$trend->date, $trend->total];
        }
        $csvData[] = [];
        $csvData[] = ['Sales by Staff'];
        $csvData[] = ['Staff', 'Total Sales'];
        foreach ($data['sales_by_staff'] as $staff => $total) {
            $csvData[] = [$staff, $total];
        }
        return $csvData;
    }

    public function exportPurchase($data)
    {
        return $this->arrayToCsv($this->purchaseRows($data));
    }

    public function purchaseRows($data): array
    {
        $csvData = [];
        $csvData[] = ['Metric', 'Value'];
        $csvData[] = ['Total Purchases', $data['total_purchases']];
        $csvData[] = ['Purchase Orders', $data['purchase_orders']];
        $csvData[] = ['Average Order Value', $data['average_orders']];
        $csvData[] = []; // empty line
        $csvData[] = ['Purchase Trend'];
        $csvData[] = ['Date', 'Total'];
        foreach ($data['trend'] as $trend) {
            $csvData[] = [$trend->date, $trend->total];
        }
        $csvData[] = [];
        $csvData[] = ['Purchase by Supplier'];
        $csvData[] = ['Supplier', 'Total Purchases'];
        foreach ($data['purchase_by_supplier'] as $supplier => $total) {
            $csvData[] = [$supplier, $total];
        }
        return $csvData;
    }

    public function exportInventory($data)
    {
        return $this->arrayToCsv($this->inventoryRows($data));
    }

    public function inventoryRows($data): array
    {
        $csvData = [];
        $csvData[] = ['Metric', 'Value'];
        $csvData[] = ['Total Products', $data['total_products']];
        $csvData[] = ['Total Inventory Value', $data['total_value']];
        $csvData[] = ['Low Stock Products', $data['low_stock']];
        $csvData[] = ['Out of Stock Products', $data['out_of_stock']];
        $csvData[] = [];
        $csvData[] = ['Stock Movement Summary'];
        $csvData[] = ['Stock In Quantity', $data['movement_summary']['stock_in_quantity']];
        $csvData[] = ['Stock Out Quantity', $data['movement_summary']['stock_out_quantity']];
        $csvData[] = ['Net Stock Change', $data['movement_summary']['net_stock_change']];
        $csvData[] = ['Movement Count', $data['movement_summary']['movement_count']];
        $csvData[] = [];
        $csvData[] = ['Movement by Source'];
        $csvData[] = ['Source', 'Quantity', 'Count'];
        foreach ($data['movement_by_source'] as $source => $movement) {
            $csvData[] = [$source, $movement['quantity'], $movement['count']];
        }
        $csvData[] = [];
        $csvData[] = ['Low Stock Items'];
        $csvData[] = ['Product', 'SKU', 'Current Stock', 'Reorder Point'];
        foreach ($data['low_stock_items'] as $item) {
            $csvData[] = [$item->name, $item->sku, $item->current_stock, $item->reorder_point];
        }
        $csvData[] = [];
        $csvData[] = ['Out of Stock Items'];
        $csvData[] = ['Product', 'SKU', 'Reorder Point'];
        foreach ($data['out_of_stock_items'] as $item) {
            $csvData[] = [$item->name, $item->sku, $item->reorder_point];
        }
        $csvData[] = [];
        $csvData[] = ['Product Distribution by Category'];
        $csvData[] = ['Category', 'Total Products'];
        foreach ($data['distribution_category'] as $dist) {
            $csvData[] = [$dist->name, $dist->total];
        }
        $csvData[] = [];
        $csvData[] = ['Inventory Value by Category'];
        $csvData[] = ['Category', 'Inventory Value'];
        foreach ($data['inventory_value_category'] as $val) {
            $csvData[] = [$val->name, $val->inventory_value];
        }
        $csvData[] = [];
        $csvData[] = ['Top Moved Products'];
        $csvData[] = ['Product', 'SKU', 'Stock In', 'Stock Out', 'Net Change', 'Total Moved'];
        foreach ($data['top_moved_products'] as $product) {
            $csvData[] = [
                $product['name'],
                $product['sku'],
                $product['stock_in'],
                $product['stock_out'],
                $product['net_change'],
                $product['total_moved'],
            ];
        }
        $csvData[] = [];
        $csvData[] = ['Recent Stock Movements'];
        $csvData[] = ['Date', 'Product', 'SKU', 'Type', 'Quantity', 'Source', 'User'];
        foreach ($data['recent_movements'] as $movement) {
            $csvData[] = [
                $movement->created_at,
                $movement->product_name,
                $movement->sku,
                $movement->movement_type,
                $movement->quantity,
                $movement->reference_type,
                $movement->user_name,
            ];
        }

        return $csvData;
    }

    public function exportPerformance($data)
    {
        return $this->arrayToCsv($this->performanceRows($data));
    }

    public function performanceRows($data): array
    {
        $csvData = [];
        $csvData[] = ['Revenue by Category'];
        $csvData[] = ['Category', 'Total Revenue'];
        foreach ($data['revenue_by_category'] as $item) {
            $csvData[] = [$item->name, $item->total];
        }
        $csvData[] = [];
        $csvData[] = ['Revenue by Brand'];
        $csvData[] = ['Brand', 'Total Revenue'];
        foreach ($data['revenue_by_brand'] as $item) {
            $csvData[] = [$item->name, $item->total];
        }
        return $csvData;
    }

    public function exportAdjustments($data)
    {
        return $this->arrayToCsv($this->adjustmentRows($data));
    }

    public function adjustmentRows($data): array
    {
        $csvData = [];
        $csvData[] = ['Metric', 'Value'];
        $csvData[] = ['Total Adjustments', $data['total_adjustments']];
        $csvData[] = ['Adjustments Value', $data['adjustments_value']];
        $csvData[] = [];
        $csvData[] = ['Adjustments by Reason'];
        $csvData[] = ['Reason', 'Count'];
        foreach ($data['adjustments_by_reason'] as $reason) {
            $csvData[] = [$reason->reason, $reason->num_reasons];
        }
        return $csvData;
    }

    public function exportProfitAndLoss($data)
    {
        return $this->arrayToCsv($this->profitAndLossRows($data));
    }

    public function profitAndLossRows($data): array
    {
        $csvData = [];
        $csvData[] = ['Metric', 'Value'];
        $csvData[] = ['Revenue', $data['revenue']];
        $csvData[] = ['Cost of Goods Sold', $data['cost_of_goods']];
        $csvData[] = ['Gross Profit', $data['gross_profit']];
        $csvData[] = ['Adjustment Loss', $data['adjustment_loss']];
        $csvData[] = ['Net Profit', $data['net_profit']];
        $csvData[] = ['Profit Margin (%)', $data['profit_margin']];

        return $csvData;
    }


    private function arrayToCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }
}
