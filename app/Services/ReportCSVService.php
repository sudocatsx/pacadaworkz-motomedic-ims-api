<?php

namespace App\Services;

class ReportCSVService
{
    public function exportSales($data)
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
        return $this->arrayToCsv($csvData);
    }

    public function exportPurchase($data)
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
        return $this->arrayToCsv($csvData);
    }

    public function exportInventory($data)
    {
        $csvData = [];
        $csvData[] = ['Metric', 'Value'];
        $csvData[] = ['Total Products', $data['total_products']];
        $csvData[] = ['Total Inventory Value', $data['total_value']];
        $csvData[] = ['Low Stock Products', $data['low_stock']];
        $csvData[] = ['Out of Stock Products', $data['out_of_stock']];
        $csvData[] = [];
        $csvData[] = ['Product Distribution by Category'];
        $csvData[] = ['Category', 'Total Products'];
        foreach ($data['ditribution_category'] as $dist) {
            $csvData[] = [$dist->name, $dist->total];
        }
        $csvData[] = [];
        $csvData[] = ['Inventory Value by Category'];
        $csvData[] = ['Category', 'Inventory Value'];
        foreach ($data['inventory_value_category'] as $val) {
            $csvData[] = [$val->name, $val->inventory_value];
        }

        return $this->arrayToCsv($csvData);
    }

    public function exportPerformance($data)
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
        return $this->arrayToCsv($csvData);
    }

    public function exportAdjustments($data)
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
        return $this->arrayToCsv($csvData);
    }

    public function exportProfitAndLoss($data)
    {
        $csvData = [];
        $csvData[] = ['Metric', 'Value'];
        $csvData[] = ['Revenue', $data['revenue']];
        $csvData[] = ['Cost of Goods Sold', $data['cost_of_goods']];
        $csvData[] = ['Gross Profit', $data['gross_profit']];
        $csvData[] = ['Adjustment Loss', $data['adjustment_loss']];
        $csvData[] = ['Net Profit', $data['net_profit']];
        $csvData[] = ['Profit Margin (%)', $data['profit_margin']];

        return $this->arrayToCsv($csvData);
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
