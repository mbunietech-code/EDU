<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningRoomGuest extends Model
{
    protected $fillable = [
        'learning_room_id',
        'user_id',
        'name',
        'admitted_at',
        'denied_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'admitted_at' => 'datetime',
            'denied_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(LearningRoom::class, 'learning_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
