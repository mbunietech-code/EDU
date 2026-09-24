<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningRoomSession extends Model
{
    protected $fillable = ['learning_room_id', 'started_by', 'ended_by', 'started_at', 'ended_at', 'peak_participants'];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = ['peak_participants' => 0];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'peak_participants' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(LearningRoom::class, 'learning_room_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function ender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LearningRoomAttendance::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(LearningRoomRecording::class)->latest();
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /** Length of the session so far (still running → up to now). */
    public function durationSeconds(): int
    {
        if (! $this->started_at) {
            return 0;
        }

        $end = $this->ended_at ?? now();

        return max(0, $end->getTimestamp() - $this->started_at->getTimestamp());
    }
}
