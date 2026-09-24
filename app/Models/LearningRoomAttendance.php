<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningRoomAttendance extends Model
{
    protected $fillable = [
        'learning_room_session_id', 'learning_room_id', 'user_id', 'role',
        'first_joined_at', 'last_seen_at', 'left_at', 'total_seconds', 'join_count',
        'removed_at', 'removed_by', 'jitsi_participant_id',
    ];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = [
        'role' => 'participant',
        'total_seconds' => 0,
        'join_count' => 0,
    ];

    protected $casts = [
        'first_joined_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'left_at' => 'datetime',
        'removed_at' => 'datetime',
        'total_seconds' => 'integer',
        'join_count' => 'integer',
    ];

    public const ROLES = ['host', 'participant'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningRoomSession::class, 'learning_room_session_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LearningRoom::class, 'learning_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    /** Query form of isPresent(). */
    public function scopePresent(Builder $q): Builder
    {
        return $q->whereNull('removed_at')
            ->where('last_seen_at', '>=', now()->subSeconds((int) config('learning.presence_timeout_seconds')))
            ->where(fn (Builder $q) => $q->whereNull('left_at')->orWhereColumn('left_at', '<', 'last_seen_at'));
    }

    /** In the call right now: not removed, pinged recently, and not left since. */
    public function isPresent(): bool
    {
        if ($this->removed_at !== null || $this->last_seen_at === null) {
            return false;
        }

        if ($this->last_seen_at->lt(now()->subSeconds((int) config('learning.presence_timeout_seconds')))) {
            return false;
        }

        return $this->left_at === null || $this->left_at->lt($this->last_seen_at);
    }

    public function isHost(): bool
    {
        return $this->role === 'host';
    }
}
