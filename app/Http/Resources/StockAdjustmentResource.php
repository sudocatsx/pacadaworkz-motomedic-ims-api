<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
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
            'product_id' => $this->product_id,
            'user' => $this->user?->name,
            'reason' => $this->reason,
            'previous_quantity' => $this->previous_quantity,
            'counted_quantity' => $this->counted_quantity,
            'delta' => $this->previous_quantity === null || $this->counted_quantity === null
                ? null
                : $this->counted_quantity - $this->previous_quantity,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
