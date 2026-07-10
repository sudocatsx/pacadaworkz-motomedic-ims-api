<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class AttributesValue extends Model
{
    use SoftDeletes;
    
    //
   
      // fillable is for mass assigment (allowed na ifill up)
       protected $fillable = [
        'attribute_id',
        'value'
    ];


      //Entity Reletionship to the Attributes
     public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * The products that belong to the AttributesValue
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attributes', 'attribute_value_id', 'product_id')
            ->using(ProductAttribute::class)
            ->withTimestamps()
            ->withPivot('deleted_at');
    }

}
