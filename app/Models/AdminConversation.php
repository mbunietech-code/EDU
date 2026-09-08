<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One thread per non-super-admin user. The "Super Admin" side is treated as
 * a single collective counterpart — any super admin can read and reply here,
 * and the line admin sees them all as one "Super Admin" conversation.
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
