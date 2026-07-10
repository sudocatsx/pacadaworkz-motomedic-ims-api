<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    // use SoftDeletes;
    // fillable is for mass assigment (allowed na ifill up)
    protected $fillable = [
        'setting_key',
        'setting_value',
        'description'
    ];
}
