<?php

namespace App\Services;

use App\Exceptions\Purchase\PurchaseReceiveException;
use App\Exceptions\Purchase\PurchaseUpdateException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseItem;
use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    //get purchase service
    public function getPurchases($search = null)
    {
        $query = PurchaseOrder::with(['supplier', 'user', 'purchase_items.product']);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {

                // Supplier match
                $q->whereHas('supplier', function ($supplierQuery) use ($search) {
                    $supplierQuery->where('name', 'LIKE', "%{$search}%");
                })

                    // OR User match
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    })

                    // OR PurchaseOrder fields
                    ->orWhere(function ($purchaseQuery) use ($search) {
                        $purchaseQuery->where('status', 'LIKE', "%{$search}%")
                            ->orWhere('notes', 'LIKE', "%{$search}%")
                            ->orWhere('total_amount', 'LIKE', "%{$search}%");
                    });
            });
        }

        return $query->paginate(10)->withQueryString();
    }

    // create purchase service
    public function createPurchase(array $data)
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            // Calculate total amount automatically
            $totalAmount = collect($items)->sum(function ($item) {
                return $item['quantity'] * $item['unit_cost'];
            });

            $data['total_amount'] = $totalAmount;
            $purchase = PurchaseOrder::create($data);

            foreach ($items as $item) {
                $purchase->purchase_items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['quantity'] * $item['unit_cost'],
                ]);
            }

            $this->activityLogService->log('Purchase', 'Create', "Created purchase order #{$purchase->id}");
            return $purchase->load('purchase_items.product');
        });
    }

    public function findPurchase($id)
    {
        return PurchaseOrder::with(['supplier', 'user', 'purchase_items.product'])->findOrFail($id);
    }

    //update purchase service
    public function updatePurchase($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $purchase = PurchaseOrder::findOrFail($id);

            if ($purchase->status === 'received') {
                throw new PurchaseUpdateException("Cannot update a received purchase order.");
            }

            $items = $data['items'] ?? null;
            unset($data['items']);

            if ($items !== null) {
                // Calculate total amount automatically
                $totalAmount = collect($items)->sum(function ($item) {
                    return $item['quantity'] * $item['unit_cost'];
                });
                $data['total_amount'] = $totalAmount;

                // Sync items: delete old items and create new ones
                $purchase->purchase_items()->delete();
                foreach ($items as $item) {
                    $purchase->purchase_items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'total_cost' => $item['quantity'] * $item['unit_cost'],
                    ]);
                }
            }

            $purchase->update($data);

            $this->activityLogService->log('Purchase', 'Update', "Updated purchase order #{$purchase->id}");
            return $purchase->load('purchase_items.product');
        });
    }

    // delete purchase service
    public function deletePurchase($id)
    {
        $purchase = PurchaseOrder::findOrFail($id);

        if ($purchase->status === 'received') {
            throw new PurchaseUpdateException("Cannot delete a received purchase order.");
        }

        $purchase->delete();
        $this->activityLogService->log('Purchase', 'Delete', "Deleted purchase order #{$purchase->id}");
        return true;
    }

    /**
     * Mark purchase order as received and update inventory with WAC calculation.
     */
    public function receivePurchase($id, $userId)
    {
        return DB::transaction(function () use ($id, $userId) {
            $purchase = PurchaseOrder::with('purchase_items.product')->findOrFail($id);

            if ($purchase->status === 'received') {
                throw new PurchaseReceiveException("Purchase order #{$id} is already received.", 400);
            }

            if ($purchase->status === 'cancelled') {
                throw new PurchaseReceiveException("Cannot receive a cancelled purchase order.", 400);
            }

            foreach ($purchase->purchase_items as $item) {
                $product = $item->product; // Updated to singular 'product'

                // Update or create inventory record
                $inventory = Inventory::firstOrCreate(
                    ['product_id' => $item->product_id],
                    ['quantity' => 0]
                );

                $oldQuantity = $inventory->quantity;
                $oldCost = $product->cost_price ?? 0;
                $newQuantity = $item->quantity;
                $newCost = $item->unit_cost;

                // Calculate Weighted Average Cost (WAC)
                if ($oldQuantity > 0) {
                    $newWac = (($oldQuantity * $oldCost) + ($newQuantity * $newCost)) / ($oldQuantity + $newQuantity);
                } else {
                    // If no existing stock, the new cost becomes the base cost
                    $newWac = $newCost;
                }

                // Update product cost price with the new WAC
                $product->update(['cost_price' => $newWac]);

                // Update inventory record
                $inventory->quantity += $newQuantity;
                $inventory->save();

                // Log stock movement
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'user_id' => $userId ?? $purchase->user_id,
                    'movement_type' => 'in',
                    'quantity' => $newQuantity,
                    'reference_type' => 'purchase',
                    'reference_id' => $purchase->id,
                    'notes' => "Received from purchase order #{$purchase->id}. Calculated WAC: {$newWac}"
                ]);
            }

            $purchase->update(['status' => 'received']);
            $this->activityLogService->log('Purchase', 'Receive', "Received purchase order #{$purchase->id}");

            return $purchase->load('purchase_items.product');
        });
    }
}
