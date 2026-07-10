<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'brand' => $this->brand?->name,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'unit_price' => floatval($this->unit_price),
            'cost_price' => floatval($this->cost_price),
            'reorder_level' => $this->reorder_level,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'current_stock' => $this->current_stock ?? $this->inventory?->quantity ?? 0,
            'location' => $this->inventory?->location,
            'attributes' => AttributesValueResource::collection($this->whenLoaded('attribute_values')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
