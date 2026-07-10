<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesTransaction extends Model
{
    // use SoftDeletes;

    // fillable is for mass assigment (allowed na ifill up)
    protected $fillable = [
        'user_id',
        'transaction_no',
        'subtotal',
        'tax',
        'discount',
        'discount_type',
        'total_amount',
        'payment_method',
        'amount_tendered',
        'change',
    ];

    // Entity Reletionship to the user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Entity Reletionship to the sales_items
    public function sales_items(): HasMany
    {
        return $this->hasMany(SalesItem::class, 'sales_transactions_id');
    }
}
