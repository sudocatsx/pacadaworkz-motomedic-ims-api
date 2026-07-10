<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'unit_price' => intval($this->unit_price),
            'product' => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
                // 'unit_price' => $this->product->unit_price,
                'image_url' => $this->product->image_url,
                'current_stock' => $this->product->inventory?->quantity - $this->quantity
            ],
        ];
    }
}
