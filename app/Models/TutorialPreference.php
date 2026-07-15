<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorialPreference extends Model
{
    protected $fillable = ['welcome_prompt_seen_at'];

    protected function casts(): array
    {
        return ['welcome_prompt_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
