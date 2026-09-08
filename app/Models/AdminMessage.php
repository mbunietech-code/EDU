<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminMessage extends Model
{
    protected $fillable = [
        'admin_conversation_id',
        'sender_id',
        'type',
        'body',
        'file_path',
        'file_name',
        'is_read',
        'read_at',
        'edited_at',
        'is_deleted',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'edited_at' => 'datetime',
        'is_deleted' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AdminConversation::class, 'admin_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
