<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
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
            'sku' =>  $this->product?->sku,
            'product_name' => $this->product?->name,
            'category' => $this->product?->category?->name,
            'brand' =>  $this->product?->brand?->name,
            'quantity' => $this->quantity,
            'location' => $this->location,
            'last_stock_in' => $this->last_stock_in,
        ];
    }
}
