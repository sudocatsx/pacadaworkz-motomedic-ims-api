<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrdersResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);

        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier->name ?? null,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name ?? null,
            'order_date' => $this->order_date,
            'expected_delivery' => $this->expected_delivery,
            'total_amount' => floatval($this->total_amount),
            'status' => $this->status,
            'notes' => $this->notes,
            'items' => PurchaseItemResource::collection($this->whenLoaded('purchase_items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
