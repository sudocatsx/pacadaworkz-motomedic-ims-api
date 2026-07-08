<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Role;
use App\Models\SystemSetting;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\belongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;


    // JWT methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'first_name',
        'last_name',
        'contact_number',
        'is_active',
        'google_id',
        'theme'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        // 'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    //Entity relationship to role
    public function role(): belongsTo
    {
        return $this->belongsTo(Role::class);
    }

    //Entity relationship to sales_ransaction
    public function sales_transactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    //Entity relationship to stock_adjustments
    public function stock_adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }


    //Entity relationship to stock_adjustments
    public function stock_movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    //Entity relationship to activity_logs
    public function activity_logs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
        return $this->hasMany(ActivityLog::class);
    }

    //Entity relationship to system_settings
    public function system_settings(): HasOne
    {
        return $this->hasOne(SystemSetting::class);
        return $this->hasOne(SystemSetting::class);
    }
}
