<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A group thread in Team Chat: any number of admins talking together.
 * Only members can read or post; super admins manage who is a member.
 *
 * A private one-to-one chat is a group with direct_key "<lower id>:<higher id>"
 * and exactly those two members — nobody else, super admins included, can open it.
 */
class AdminGroup extends Model
{
    protected $fillable = ['name', 'direct_key', 'created_by'];

    public static function directKey(User $a, User $b): string
    {
        return min($a->id, $b->id).':'.max($a->id, $b->id);
    }

    /** The private chat between two admins, created on first use. */
    public static function directBetween(User $a, User $b): self
    {
        $key = self::directKey($a, $b);

        if ($group = self::where('direct_key', $key)->first()) {
            return $group;
        }

        try {
            $group = self::create(['name' => 'Private chat', 'direct_key' => $key, 'created_by' => $a->id]);
        } catch (UniqueConstraintViolationException $e) {
            // The other admin opened it at the same moment.
            return self::where('direct_key', $key)->firstOrFail();
        }

        $group->members()->sync([$a->id, $b->id]);

        return $group;
    }

    public function isDirect(): bool
    {
        return $this->direct_key !== null;
    }

    /** @return list<int> the two user ids of a private chat */
    public function directUserIds(): array
    {
        return $this->isDirect() ? array_map('intval', explode(':', $this->direct_key)) : [];
    }

    /** In a private chat, the person on the other side from $viewer. */
    public function otherMember(User $viewer): ?User
    {
        return $this->members->firstWhere('id', '!=', $viewer->id);
    }

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
