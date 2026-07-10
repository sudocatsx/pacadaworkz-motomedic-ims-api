<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesItemResource extends JsonResource
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
            // 'sales_transactions_id' => $this->sales_transactions_id,
            'product_id' => $this->product_id,
            'unit_price' => intval($this->unit_price),
            'quantity' => $this->quantity,
        ];
    }
}
