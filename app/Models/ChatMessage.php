<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'is_from_admin',
        'type',
        'body',
        'file_path',
        'file_name',
        'is_read',
        'read_at',
        'edited_at',
        'is_deleted',
        'is_auto',
    ];

    protected $casts = [
        'is_from_admin' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'edited_at' => 'datetime',
        'is_deleted' => 'boolean',
        'is_auto' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}