<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningVideoProgress extends Model
{
    protected $table = 'learning_video_progress';

    protected $fillable = [
        'user_id', 'learning_video_id', 'position_seconds', 'max_position_seconds',
        'watched_seconds', 'duration_seconds', 'percent', 'play_count',
        'completed_at', 'last_watched_at',
    ];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = [
        'position_seconds' => 0,
        'max_position_seconds' => 0,
        'watched_seconds' => 0,
        'percent' => 0,
        'play_count' => 0,
    ];

    protected $casts = [
        'position_seconds' => 'integer',
        'max_position_seconds' => 'integer',
        'watched_seconds' => 'integer',
        'duration_seconds' => 'integer',
        'percent' => 'integer',
        'play_count' => 'integer',
        'completed_at' => 'datetime',
        'last_watched_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(LearningVideo::class, 'learning_video_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
