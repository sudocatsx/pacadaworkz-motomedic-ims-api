<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProductAttribute extends Pivot
{
    use SoftDeletes;
    
    //


    protected $table = 'product_attributes';
 
    protected $dates = ['deleted_at'];

// fillable is for mass assigment (allowed na ifill up)
      protected $fillable = [
        'attribute_value_id',
        'product_id'
    ];


    //  public function attribute(): BelongsTo
    // {
    //     return $this->belongsTo(Attribute::class);
    // }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes_value(): BelongsTo
    {
        return $this->belongsTo(AttributesValue::class, 'attribute_value_id');
    }

}
