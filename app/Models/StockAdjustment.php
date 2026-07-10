<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    public $timestamps = false;

    // fillable is for mass assigment (allowed na ifill up)
    protected $fillable = [
        'user_id',
        'product_id',
        'reason',
        'previous_quantity',
        'counted_quantity',
        'notes',
    ];

    // Entity relationship to the user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
