<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    //

    // fillable is for mass assigment (allowed na ifill up)
    protected $fillable = [
        'category_id',
        'brand_id',
        'sku',
        'name',
        'description',
        'unit_price',
        'cost_price',
        'reorder_level',
        'image_url',
        'image_original_name',
        'image_mime_type',
        'image_size_bytes',
        'image_source',
        'is_active',
    ];

    // Entity Reletionship to the brand
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // Entity Reletionship to the inventory
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    // Entity Relationship to the Category
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Entity Relationship to the sales_items
    public function sales_items(): HasMany
    {
        return $this->hasMany(SalesItem::class);
    }

    // Entity Relationship to the purchase_item
    public function purchase_items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    // Entity Relationship to the stock_movements
    public function stock_movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // Entity Reletionship to the attribute_values
    public function attribute_values(): BelongsToMany
    {
        return $this->belongsToMany(AttributesValue::class, 'product_attributes', 'product_id', 'attribute_value_id')
            ->using(ProductAttribute::class) // the pivot model
            ->withTimestamps() // for created at and updated at
            ->withPivot('deleted_at'); // for deletion
    }
}
