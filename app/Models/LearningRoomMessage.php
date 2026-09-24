<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningRoomMessage extends Model
{
    protected $fillable = [
        'learning_room_id', 'learning_room_session_id', 'user_id', 'type', 'body',
        'is_answered', 'answered_by', 'answered_at', 'is_deleted',
    ];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = [
        'type' => 'chat',
        'is_answered' => false,
        'is_deleted' => false,
    ];

    protected $casts = [
        'is_answered' => 'boolean',
        'is_deleted' => 'boolean',
        'answered_at' => 'datetime',
    ];

    public const TYPES = ['chat', 'question', 'announcement'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(LearningRoom::class, 'learning_room_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningRoomSession::class, 'learning_room_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function isQuestion(): bool
    {
        return $this->type === 'question';
    }
}
