<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A teaching material (slides, worksheet, …) the host shares in a live room.
 * Stored on the private learning disk and downloaded through
 * learn.rooms.materials.download (authorised per request).
 */
class LearningRoomMaterial extends Model
{
    protected $fillable = [
        'learning_room_id', 'title', 'disk', 'path', 'original_name', 'mime', 'size_bytes', 'uploaded_by',
    ];

    protected $attributes = ['size_bytes' => 0];

    protected $casts = ['size_bytes' => 'integer'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(LearningRoom::class, 'learning_room_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }
}
