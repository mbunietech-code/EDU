<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "call me on video" request from a member. While it is ringing every
 * support admin sees and hears it; the first to accept wins (an atomic
 * update on status = ringing), the rest stop ringing.
 */
class VideoHelpRequest extends Model
{
    public const RINGING = 'ringing';

    public const ACCEPTED = 'accepted';

    public const MISSED = 'missed';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id', 'learning_room_id', 'status', 'reason',
        'accepted_by', 'declined_by', 'ring_until', 'accepted_at', 'ended_at',
    ];

    protected $attributes = [
        'status' => self::RINGING,
    ];

    protected $casts = [
        'declined_by' => 'array',
        'ring_until' => 'datetime',
        'accepted_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LearningRoom::class, 'learning_room_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function scopeRinging(Builder $q): Builder
    {
        return $q->where('status', self::RINGING);
    }

    public function isRinging(): bool
    {
        return $this->status === self::RINGING;
    }

    /** @return list<int> */
    public function declinedIds(): array
    {
        return array_values(array_map('intval', (array) ($this->declined_by ?? [])));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::RINGING => 'Ringing',
            self::ACCEPTED => 'Answered',
            self::MISSED => 'Missed',
            self::CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }
}
