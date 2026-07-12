<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesTransactionResource extends JsonResource
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
            'user_id' => $this->user_id,
            'cashier' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'transaction_no' => $this->transaction_no,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'discount_type' => $this->discount_type,
            'total_amount' => (float) $this->total_amount,
            'payment_method' => $this->payment_method,
            'amount_tendered' => (float) $this->amount_tendered,
            'change' => (float) $this->change,
            'status' => $this->status,
            'refund_amount' => (float) ($this->refund_amount ?? 0),
            'refund_reason' => $this->refund_reason,
            'refunded_at' => $this->refunded_at,
            'net_sales' => max(0, (float) $this->total_amount - (float) ($this->refund_amount ?? 0)),
            'created_at' => $this->created_at,
            'sales_item' => SalesItemResource::collection($this->whenLoaded('sales_items')),
            'authorization_history' => TransactionAuthorizationResource::collection($this->whenLoaded('authorizations')),
        ];
    }
}
