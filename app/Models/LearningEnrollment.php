<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningEnrollment extends Model
{
    protected $fillable = ['user_id', 'learning_course_id', 'source', 'enrolled_by', 'enrolled_at', 'completed_at'];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = ['source' => 'self'];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const SOURCES = ['self', 'admin'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    public function enroller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
