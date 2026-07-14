<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    //

    // fillable is for mass assigment (allowed na ifill up)
    protected $fillable = [
        'name',
        'description',
    ];

    // Entity Reletionship to the products
    public function products(): HasMany
    {
        return $this->HasMany(Product::class);
    }
}
