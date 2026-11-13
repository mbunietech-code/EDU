<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a website chatbot conversation (see ChatbotService). */
class ChatbotMessage extends Model
{
    protected $fillable = ['session_key', 'user_id', 'role', 'content', 'provider', 'model', 'status', 'ip'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
