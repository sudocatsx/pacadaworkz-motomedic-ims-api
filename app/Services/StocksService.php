<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StocksService
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function getProductMovements(int $productId, int $perPage = 10): LengthAwarePaginator
    {
        Product::findOrFail($productId);

        return StockMovement::query()
            ->with(['user', 'product', 'adjustment'])
            ->where('product_id', $productId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createCountAdjustment(int $productId, array $data): array
    {
        return DB::transaction(function () use ($productId, $data) {
            $product = Product::findOrFail($productId);
            $inventory = Inventory::query()
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            $previousQuantity = (int) $inventory->quantity;
            $countedQuantity = (int) $data['counted_quantity'];
            $delta = $countedQuantity - $previousQuantity;

            if ($delta === 0) {
                throw ValidationException::withMessages([
                    'counted_quantity' => ['The counted quantity is unchanged.'],
                ]);
            }

            $adjustment = StockAdjustment::create([
                'product_id' => $productId,
                'user_id' => Auth::id(),
                'reason' => $data['reason'],
                'previous_quantity' => $previousQuantity,
                'counted_quantity' => $countedQuantity,
                'notes' => $data['notes'] ?? null,
                'unit_cost' => $product->cost_price,
            ]);

            $inventory->update(['quantity' => $countedQuantity]);

            $movement = StockMovement::create([
                'product_id' => $productId,
                'user_id' => Auth::id(),
                'movement_type' => $delta > 0 ? 'in' : 'out',
                'quantity' => abs($delta),
                'reference_type' => 'adjustment',
                'reference_id' => $adjustment->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->activityLogService->log(
                module: 'Products',
                action: 'Adjust Stock',
                description: "Counted {$product->name} from {$previousQuantity} to {$countedQuantity}",
                userId: Auth::id()
            );

            return [
                'product' => $product->load(['category', 'brand', 'inventory', 'attribute_values.attribute']),
                'adjustment' => $adjustment->load(['user', 'product']),
                'movement' => $movement->load(['user', 'product', 'adjustment']),
            ];
        });
    }
}
