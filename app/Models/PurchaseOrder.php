<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    //

    // fillable is for mass assigment (allowed na ifill up)
    protected $fillable = [
        'supplier_id',
        'user_id',
        'order_date',
        'expected_delivery',
        'total_amount',
        'status',
        'notes',
    ];

    // Entity Reletionship to the supplier
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // Entity Reletionship to the purchase_items
    public function purchase_items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    // Entity Reletionship to the user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
