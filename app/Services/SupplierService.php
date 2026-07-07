<?php
namespace App\Services;

use App\Models\Supplier;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierService
{
    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }
    public function getAllSuppliers($search = null, $perPage = 10)
    {
        $query = Supplier::query();

        if ($search) {
            $search = strtolower($search);
            $query->where(function ($query) use ($search) {
                $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(contact_person) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"]);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function getSupplierById($id)
    {
        return Supplier::findOrFail($id);
    }

    public function createSupplier(array $data)
    {
        $supplier = Supplier::create($data);
        $this->activityLogService->log('Supplier', 'Created', 'Created supplier: ' . $supplier->name, Auth::id());
        return $supplier;
    }

    public function updateSupplier(array $data, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $oldData = $supplier->toArray();
        $supplier_name = $supplier->name;
        $supplier->update($data);
        $this->activityLogService->log('Supplier', 'Updated', "Update supplier info #{$supplier_name}", Auth::id());
        return $supplier;
    }

    public function deleteSupplier($id)
    {
        $supplier = Supplier::findOrFail($id);

        if ($supplier->purchase_orders()->exists() || $supplier->inventory()->exists()) {
            throw new ConflictHttpException('Supplier cannot be deleted while purchases or inventory records use it.');
        }

        $this->activityLogService->log('Supplier', 'Deleted', 'Deleted supplier: ' . $supplier->name, Auth::id());
        $supplier->delete();
    }
}
