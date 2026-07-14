<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    // use SoftDeletes;

    // Disable timestamps because table only has created_at
    public $timestamps = false;

    // fillable is for mass assigment (allowed na ifill up)
    protected $fillable = [
        'product_id',
        'user_id',
        'movement_type',
        'quantity',
        'reference_type',
        'reference_id',
        'notes',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'reference_id');
    }
}
