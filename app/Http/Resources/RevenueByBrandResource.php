<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RevenueByBrandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            $this->brand_name => (float) $this->total_revenue,
        ];
    }

    /**
     * Helper to transform the collection into the desired key-value format.
     */
    public static function toKeyValue($collection)
    {
        return $collection->mapWithKeys(function ($item) {
            return [$item->brand_name => (float) $item->total_revenue];
        })->toArray();
    }
}
