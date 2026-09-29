<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningRoomPollVote extends Model
{
    protected $fillable = ['learning_room_poll_id', 'user_id', 'choice'];

    protected $casts = ['choice' => 'integer'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(LearningRoomPoll::class, 'learning_room_poll_id');
    }
}
