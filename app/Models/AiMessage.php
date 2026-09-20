<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'tools_used'];

    protected $casts = [
        'tools_used' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }
}
