<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RevenueByCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Since we want the output to be { "Category Name": numeric_value },
        // and this resource might be used on a collection, 
        // we can handle the mapping here or in a static method.
        
        // However, standard JsonResource::collection() will return [{...}, {...}].
        // The user wants a single object with key-value pairs.
        
        return [
            $this->category_name => (float) $this->total_revenue,
        ];
    }

    /**
     * Helper to transform the collection into the desired key-value format.
     */
    public static function toKeyValue($collection)
    {
        return $collection->mapWithKeys(function ($item) {
            return [$item->category_name => (float) $item->total_revenue];
        })->toArray();
    }
}
