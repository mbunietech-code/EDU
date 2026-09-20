<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminGroupMessage extends Model
{
    protected $fillable = [
        'admin_group_id',
        'sender_id',
        'type',
        'body',
        'file_path',
        'file_name',
        'edited_at',
        'is_deleted',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
        'is_deleted' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AdminGroup::class, 'admin_group_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
