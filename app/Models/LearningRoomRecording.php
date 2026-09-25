<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningRoomRecording extends Model
{
    protected $fillable = [
        'learning_room_id', 'learning_room_session_id', 'source', 'status',
        'disk', 'path', 'original_name', 'mime', 'size_bytes', 'duration_seconds',
        'external_id', 'external_url', 'error', 'is_shared', 'learning_video_id', 'uploaded_by',
    ];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = [
        'status' => 'ready',
        'is_shared' => false,
    ];

    protected $casts = [
        'is_shared' => 'boolean',
        'size_bytes' => 'integer',
        'duration_seconds' => 'integer',
    ];

    /** upload = file added in the studio; livekit = recorded by our self-hosted SFU (Egress). */
    public const SOURCES = ['upload', 'livekit'];

    public const STATUSES = ['processing', 'ready', 'failed'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(LearningRoom::class, 'learning_room_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningRoomSession::class, 'learning_room_session_id');
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(LearningVideo::class, 'learning_video_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function sizeLabel(): string
    {
        return LearningVideo::humanBytes($this->size_bytes);
    }
}
