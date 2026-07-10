<?php

namespace App\Services;

use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;

class StocksService
{
    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }
   
    //show stock adjustments service
  public function showStockAdjustments(?string $search = null, int $perPage = 15): LengthAwarePaginator
{
    $query = StockAdjustment::query();

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('reason', 'ILIKE', "%{$search}%")
              ->orWhere('user_id', 'ILIKE', "%{$search}%");
        });
    }

    return $query->paginate($perPage)->withQueryString();
}

    // get specific stock adjustment
    public function showStockAdjustmentsById(int $id): StockAdjustment
    {
        return StockAdjustment::findOrFail($id);
    }

    // Export stock adjustment data.
    
    public function exportStockAdjustments()
    {
        $query = StockAdjustment::with('user');

        $adjustments = $query->get();

        $fileName = 'stock-adjustments-' . uniqid() . '.csv';
        $filePath = storage_path('app/private/' . $fileName);

        $handle = fopen($filePath, 'w');

        // Add CSV headers
        fputcsv($handle, [
            'ID',
            'User Name',
            'Reason',
            'Description',
            'Created At',
        ]);

        // Add CSV rows
        foreach ($adjustments as $adjustment) {
            fputcsv($handle, [
                $adjustment->id,
                $adjustment->user->name,
                $adjustment->reason,
                $adjustment->description,
                $adjustment->created_at,
            ]);
        }

        fclose($handle);

        return $filePath;
    }

    
    //  Retrieve and filter stock movements.
     
  public function getStockMovements(?string $search = null, int $perPage = 15): LengthAwarePaginator
{
    $query = StockMovement::with(['product.brand', 'user']);

    if ($search) {
        $query->where(function ($q) use ($search) {

            // product name
            $q->whereHas('product', function ($p) use ($search) {
                $p->where('name', 'ILIKE', "%{$search}%");
            })

            // brand name
            ->orWhereHas('product.brand', function ($b) use ($search) {
                $b->where('name', 'ILIKE', "%{$search}%");
            })

            // user name
            ->orWhereHas('user', function ($u) use ($search) {
                $u->where('name', 'ILIKE', "%{$search}%");
            })

            // movement type (in / out / adjustment)
            ->orWhere('movement_type', 'ILIKE', "%{$search}%");

           
        });
    }

    return $query->paginate($perPage)->withQueryString();
}


    // Find a specific stock movement by ID.
   
    public function showStockMovementsById(int $id): StockMovement
    {
        return StockMovement::findOrFail($id);
    }

    
     // Export stock movements data.
  
    public function exportStockMovements(array $filters = [])
    {
        $query = StockMovement::with(['product.brand', 'user']);

        $movements = $query->get();

        $fileName = 'stock-movements-' . uniqid() . '.csv';
        $filePath = storage_path('app/private/' . $fileName);

        $handle = fopen($filePath, 'w');

        // Add CSV headers
        fputcsv($handle, [
            'ID',
            'Product Name',
            'Brand Name',
            'User Name',
            'Movement Type',
            'Quantity',
            'Created At',
        ]);

        // Add CSV rows
        foreach ($movements as $movement) {
            fputcsv($handle, [
                $movement->id,
                $movement->product->name,
                $movement->product->brand->name,
                $movement->user->name,
                $movement->movement_type,
                $movement->quantity,
                $movement->created_at,
            ]);
        }

        fclose($handle);

        return $filePath;
    }

    // Retrieve stock movements by product ID.
    
    public function getStockMovementsbyProductId(int $productId, array $filters = []): LengthAwarePaginator
    {
        $filters['product_id'] = $productId;
        return $this->getStockMovements($filters);
    }

    // Create a new stock adjustment
    public function createStockAdjustment(array $data): StockAdjustment
    {
        return DB::transaction(function () use ($data) {
            // 1. Create StockAdjustment
            $adjustment = StockAdjustment::create([
                'user_id' => auth()->id(),
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);

            // 2. Update Inventory
            $inventory = Inventory::where('product_id', $data['product_id'])->firstOrFail();
            
            $inventory->quantity += $data['quantity'];
            $inventory->save();

            // 3. Create StockMovement
            StockMovement::create([
                'product_id' => $data['product_id'],
                'user_id' => auth()->id(),
                'movement_type' => $data['quantity'] > 0 ? 'in' : 'out',
                'quantity' => abs($data['quantity']),
                'reference_type' => 'adjustment',
                'reference_id' => $adjustment->id,
                'notes' => $data['notes'] ?? null,
            ]);

            // Log activity after successful transaction
            $this->activityLogService->log(
                'Stock Adjustment',
                'created',
                'Stock Adjustment created (ID: ' . $adjustment->id . ', Reason: ' . $adjustment->reason . ', Quantity: ' . $data['quantity'] . ')',
                Auth::id()
            );

            return $adjustment;
        });
    }

    // Update an existing stock adjustment
    public function updateStockAdjustment(int $id, array $data): StockAdjustment
    {
        $adjustment = StockAdjustment::findOrFail($id);
        $oldReason = $adjustment->reason; // Capture old reason for log description
        $adjustment->update($data);

        $this->activityLogService->log(
            'Stock Adjustment',
            'updated',
            'Stock Adjustment ID: ' . $adjustment->id . ' updated. Old reason: ' . $oldReason . ', New reason: ' . ($data['reason'] ?? $oldReason),
            Auth::id()
        );

        return $adjustment;
    }
}