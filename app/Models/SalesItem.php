<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesItem extends Model
{
    // use SoftDeletes;

    // fillable is for mass assigment (allowed na ifill up)
    protected $fillable = [
        'sales_transactions_id',
        'product_id',
        'quantity',
        'quantity_returned',
        'unit_price',
        'unit_cost',
        'allocated_discount',
        'net_line_total',
        'refunded_line_amount',
    ];

    // Entity Relationship to the sales_transaction
    public function sales_transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transactions_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
