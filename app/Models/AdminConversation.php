<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Legacy Team Chat thread (before 2026-09-29): one per admin, readable by
 * every super admin. Kept only as history — alter 0016 copied each thread
 * into a private AdminGroup chat (peer_id / migrated_at record where it went).
 */
class AdminConversation extends Model
{
    protected $fillable = ['admin_id'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AdminMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(AdminMessage::class)->latestOfMany();
    }
}
