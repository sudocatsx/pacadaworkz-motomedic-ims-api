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
        'status',
        'refund_amount',
        'refund_reason',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'refunded_at' => 'datetime',
        ];
    }

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

    public function authorizations(): HasMany
    {
        return $this->hasMany(TransactionAuthorization::class)->orderBy('authorized_at');
    }
}
