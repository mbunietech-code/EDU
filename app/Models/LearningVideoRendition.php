<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningVideoRendition extends Model
{
    protected $fillable = ['learning_video_id', 'quality', 'height', 'disk', 'path', 'mime', 'size_bytes'];

    protected $casts = [
        'height' => 'integer',
        'size_bytes' => 'integer',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(LearningVideo::class, 'learning_video_id');
    }

    public function sizeLabel(): string
    {
        return LearningVideo::humanBytes($this->size_bytes);
    }
}
