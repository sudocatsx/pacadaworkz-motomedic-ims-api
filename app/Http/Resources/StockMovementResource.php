<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
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
            'product_id' => $this->product_id,
            'product' => $this->product->name,
            'brand' => $this->product->brand->name,
            'category' => $this->product->category->name,
            'user' => $this->user->name,
            'quantity' => $this->quantity,
            'movement_type' => $this->movement_type,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reason' => $this->reference_type === 'adjustment' ? $this->adjustment?->reason : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
