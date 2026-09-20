<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A group thread in Team Chat: any number of admins talking together.
 * Only members can read or post; super admins manage who is a member.
 */
class AdminGroup extends Model
{
    protected $fillable = ['name', 'created_by'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'admin_group_members')
            ->withPivot('last_read_message_id')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AdminGroupMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(AdminGroupMessage::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /** Messages from others this member hasn't opened yet. */
    public function unreadFor(User $user): int
    {
        $lastRead = (int) ($this->members()->whereKey($user->id)->first()?->pivot->last_read_message_id ?? 0);

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_deleted', false)
            ->where('id', '>', $lastRead)
            ->count();
    }

    public function markReadFor(User $user): void
    {
        $latest = (int) $this->messages()->max('id');

        if ($latest > 0) {
            $this->members()->updateExistingPivot($user->id, ['last_read_message_id' => $latest]);
        }
    }
}
