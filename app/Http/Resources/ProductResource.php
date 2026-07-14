<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'brand' => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ] : null,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'unit_price' => floatval($this->unit_price),
            'cost_price' => floatval($this->cost_price),
            'reorder_level' => $this->reorder_level,
            'image_url' => $this->publicImageUrl(),
            'is_active' => $this->is_active,
            'current_stock' => $this->current_stock ?? $this->inventory?->quantity ?? 0,
            'location' => $this->inventory?->location,
            'stock_status' => $this->stockStatus(),
            'inventory' => $this->inventory ? [
                'id' => $this->inventory->id,
                'quantity' => $this->inventory->quantity,
                'location' => $this->inventory->location,
                'last_stock_in' => $this->inventory->last_stock_in,
                'updated_at' => $this->inventory->updated_at,
            ] : null,
            'attributes' => AttributesValueResource::collection($this->whenLoaded('attribute_values')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function publicImageUrl(): ?string
    {
        if (! $this->image_url) {
            return null;
        }

        if (str_starts_with($this->image_url, 'http://') || str_starts_with($this->image_url, 'https://')) {
            return $this->image_url;
        }

        $path = ltrim(str_replace('/storage/', '', $this->image_url), '/');

        return Storage::disk('public')->url($path);
    }

    private function stockStatus(): string
    {
        $quantity = (int) ($this->current_stock ?? $this->inventory?->quantity ?? 0);
        if ($quantity === 0) {
            return 'out_of_stock';
        }

        return $quantity <= (int) $this->reorder_level ? 'low_stock' : 'in_stock';
    }
}
