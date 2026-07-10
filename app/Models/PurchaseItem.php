<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    public $timestamps = false;
    
    //

    // fillable is for mass assigment (allowed na ifill up)
      protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_cost',
        'total_cost'
    ];

     //Entity Reletionship to the purchase_order
      public function purchase_order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    //Entity Relationship to the product
      public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

}
