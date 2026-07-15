<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorialProgress extends Model
{
    protected $table = 'tutorial_progress';

    protected $fillable = ['tutorial_key', 'content_version', 'status', 'current_step', 'started_at', 'completed_at', 'skipped_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'skipped_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
