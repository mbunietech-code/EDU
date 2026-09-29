<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One pen stroke on a live class whiteboard (see RoomBoardService for the data format). */
class LearningRoomBoardStroke extends Model
{
    protected $fillable = ['learning_room_session_id', 'user_id', 'uid', 'data'];

    protected $casts = ['data' => 'array'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LearningRoomSession::class, 'learning_room_session_id');
    }
}
