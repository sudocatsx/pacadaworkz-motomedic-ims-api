<?php

namespace App\Http\Controllers\API;

use Exception;
use App\Services\ReportsService;
use App\Http\Controllers\API\Controller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
class ReportsController extends Controller
{
    protected $reportsService;

    public function __construct(ReportsService $reportsService)
    {
        $this->reportsService = $reportsService;
    }

    private function resolveDateRange(Request $request): array
    {
        $period = $request->query('period');

        if (!$period || $period === 'custom') {
            return [
                $request->query('start_date', null),
                $request->query('end_date', null),
            ];
        }

        $today = Carbon::today();

        return match ($period) {
            'daily' => [
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
            ],
            'weekly' => [
                $today->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay(),
                $today->copy()->endOfWeek(Carbon::SATURDAY)->endOfDay(),
            ],
            'monthly' => [
                $today->copy()->startOfMonth()->startOfDay(),
                $today->copy()->endOfMonth()->endOfDay(),
            ],
            'quarterly' => [
                $today->copy()->startOfQuarter()->startOfDay(),
                $today->copy()->endOfQuarter()->endOfDay(),
            ],
            'yearly' => [
                $today->copy()->startOfYear()->startOfDay(),
                $today->copy()->endOfYear()->endOfDay(),
            ],
            default => [
                $request->query('start_date', null),
                $request->query('end_date', null),
            ],
        };
    }
 
    // show all sales report
     public function showSalesReport(Request $request){
          try {
           
             [$start, $end] = $this->resolveDateRange($request);


             $result = $this->reportsService->getSalesReport($start,$end);
             return response()->json([
                'success' => true,
                'data' => $result
             ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occured'], 500);
        }
     }

//show all purchases
     public function showPurchases(Request $request){
        try {
            [$start, $end] = $this->resolveDateRange($request);
   
             $result = $this->reportsService->getPurchases($start,$end);

             return response()->json([
                'success' => true,
                'data' => $result
             ]);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
              return response()->json(['message' => 'An error occured'], 500);
        }

     }

// show inventory
   public function showInventory(Request $request){
     try {
           [$start, $end] = $this->resolveDateRange($request);

             $result = $this->reportsService->getInventory($start,$end);

               return response()->json([
                'success' => true,
                'data' => $result
             ]); 
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occured'], 500);
        }
   }

//show performance
   public function showPerformance(Request $request){
                try {
           [$start, $end] = $this->resolveDateRange($request);

             $result = $this->reportsService->getPerformance($start,$end);

               return response()->json([
                'success' => true,
                'data' => $result
             ]); 
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occured'], 500);
        }

   }

   
   
//show stock adjustments
   public function showStockAdjustments(Request $request){
                try {
           [$start, $end] = $this->resolveDateRange($request);

             $result = $this->reportsService->getStockAdjustments($start,$end);

               return response()->json([
                'success' => true,
                'data' => $result
             ]); 
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occured'], 500);
        }

   }
 // profit loss
    public function showProfitLossReport(Request $request)
    {
        try {
             [$start, $end] = $this->resolveDateRange($request);
             
             $result = $this->reportsService->getProfitLossReport($start,$end);
             return response()->json([
                'success' => true,
                'data' => $result
             ]); 
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    // export as csv 
      public function showReportCSV($type,Request $request){
         try {
             [$start, $end] = $this->resolveDateRange($request);
             
             $result = $this->reportsService->getReportCSV($start,$end,$type);
             return $result; 
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Report not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
      }

    }
