<?php

namespace App\Http\Controllers\API;

use App\Services\DashboardService;
use App\Http\Controllers\API\Controller;
use App\Http\Resources\RevenueByBrandResource;
use App\Http\Resources\RevenueByCategoryResource;
use App\Http\Resources\SalesTrendResource;
use App\Http\Resources\TopProductResource;
use App\Http\Resources\DashboardActivityLogResource;
use Illuminate\Http\Request;

class DashboardController
{

    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    //show statistics dashboard
    public function showStats()
    {
        try {

            $result = $this->dashboardService->getStats();

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occured'], 500);
        }
    }


    //show sales trend
    public function showSalesTrend()
    {
        try {
            $result = $this->dashboardService->getSalesTrend();

            return response()->json([
                'success' => true,
                'data' => SalesTrendResource::format($result)
            ]);
        } catch (\Exception $e) {
            return response()->json(
                ['error' => 'An error occured'],
                500
            );
        }
    }


    //show top products
    public function showTopProducts()
    {
        try {
            $result = $this->dashboardService->getTopProducts();

            return response()->json([
                'success' => true,
                'data' => TopProductResource::format($result)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    // show revenue per category
    public function showRevenueByCategory()
    {
        try {
            $result = $this->dashboardService->getRevenueByCategory();

            return response()->json([
                'success' => true,
                'data' => RevenueByCategoryResource::toKeyValue($result)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // show revenue per brand
    public function showRevenueByBrand()
    {
        try {
            $result = $this->dashboardService->getRevenueByBrand();

            return response()->json([
                'success' => true,
                'data' => RevenueByBrandResource::toKeyValue($result)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // show inventory overview
    public function showInventoryOverview()
    {
        try {
            $result = $this->dashboardService->getInventoryOverview();


            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function showRecentActivities()
    {
        try {
            $result = $this->dashboardService->getRecentActivities();

            return response()->json([
                'success' => true,
                'data' => DashboardActivityLogResource::collection($result)
                // 'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
