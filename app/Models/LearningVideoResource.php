<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningVideoResource extends Model
{
    protected $fillable = [
        'learning_video_id', 'title', 'type', 'url', 'disk', 'path',
        'original_name', 'mime', 'size_bytes', 'position',
    ];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = ['position' => 0];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public const TYPES = ['file', 'link'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(LearningVideo::class, 'learning_video_id');
    }

    public function isLink(): bool
    {
        return $this->type === 'link';
    }

    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    public function sizeLabel(): string
    {
        return LearningVideo::humanBytes($this->size_bytes);
    }
}
