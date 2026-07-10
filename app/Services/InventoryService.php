<?php
namespace App\Services;
use App\Models\Inventory;

class InventoryService
{
    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function getAllInventory($search = null)
    {
        $query = Inventory::with(['product.brand', 'product.category']);

        if ($search) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhereHas('brand', function ($q) use ($search) {
                        $q->where('name', 'ILIKE', "%{$search}%");
                    });
            });
        }

        return $query->paginate(10)->withQueryString();
    }

    public function getInventoryById($id)
    {
        $inventory = Inventory::findOrFail($id);

        return $inventory;
    }

    public function createInventory(array $data)
    {
        $inventory = Inventory::create($data);
        $this->activityLogService->log('Inventory', 'Create', "Created inventory item #{$inventory->id}");
        return $inventory;
    }

    public function updateInventory(array $data, $id)
    {
        $inventory = Inventory::findOrFail($id);
        $inventory->update($data);

        $this->activityLogService->log('Inventory', 'Update', "Updated inventory item #{$inventory->id}");
        return $inventory;
    }

    public function deleteInventory($id)
    {
        $inventory = Inventory::findOrFail($id);
        $this->activityLogService->log('Inventory', 'Delete', "Deleted inventory item #{$inventory->id}");
        $inventory->delete();

        return true;
    }
}
