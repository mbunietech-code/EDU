<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A poll (or quiz, when correct_option is set) asked by the host during a live class. */
class LearningRoomPoll extends Model
{
    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 6;

    protected $fillable = [
        'learning_room_id', 'learning_room_session_id', 'created_by',
        'question', 'options', 'correct_option', 'closed_at',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_option' => 'integer',
        'closed_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(LearningRoom::class, 'learning_room_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(LearningRoomPollVote::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function isQuiz(): bool
    {
        return $this->correct_option !== null;
    }
}
