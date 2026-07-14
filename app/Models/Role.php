<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    //

    // fillable is for mass assigment (allowed na ifill up)
    protected $fillable = [
        'role_name',
        'description',
    ];

    // Reletionship to the user
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Entity Reletionship to the permissions via role_permissions
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->using(RolePermission::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }
}
