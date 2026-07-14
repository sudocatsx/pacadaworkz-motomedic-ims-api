<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BrandService
{
    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    // get all brands
    public function getAllBrands($search = null, $perPage = 10)
    {

        $query = Brand::query();

        if ($search) {
            $search = strtolower($search);
            $query->where(function ($query) use ($search) {
                $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
            });
        }

        return $query->withCount('products')->paginate($perPage)->withQueryString();

    }

    // get brands by id
    public function getBrandById($id)
    {

        return Brand::findOrFail($id);

    }

    // create new brand
    public function create(array $data)
    {

        $brand = Brand::create($data);

        $this->activityLogService->log(
            'Brand',
            'created',
            'Brand created: '.$brand->name,
            Auth::id()
        );

        return $brand;
    }

    // update brand

    public function update(array $data, $id)
    {

        $brand = Brand::findOrFail($id);

        $brand->update($data);

        $this->activityLogService->log(

            'Brand',

            'updated',

            'Brand updated: '.$brand->name,

            Auth::id()

        );

        return $brand;

    }

    // delete brand

    public function delete($id)
    {

        $brand = Brand::findOrFail($id);

        $brandName = $brand->name; // Capture name before deletion

        if ($brand->products()->exists()) {
            throw new ConflictHttpException('Brand cannot be deleted while products are assigned to it.');
        }

        $brand->delete();

        $this->activityLogService->log(

            'Brand',

            'deleted',

            'Brand deleted: '.$brandName,

            Auth::id()

        );

        return true; // Or return as per original logic if it returned something else

    }
}
