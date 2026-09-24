<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningTopic extends Model
{
    protected $fillable = ['learning_course_id', 'title', 'description', 'position'];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = ['position' => 0];

    public function course(): BelongsTo
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(LearningVideo::class)->orderBy('position')->orderBy('id');
    }
}
