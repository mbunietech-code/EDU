<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public const INBOX_STATUSES = ['all', 'unread', 'awaiting', 'replied', 'online'];

    public const INBOX_PERIODS = ['today', '7d', '30d'];

    /**
     * Admin inbox: real customers only, narrowed by a search (name, email or
     * message text) and a period (had a message since). Used by the web
     * Chats page and the app's admin API so both filter the same way.
     */
    public function scopeAdminInbox(Builder $query, ?string $search = null, ?string $period = null): Builder
    {
        $search = trim((string) $search);
        $since = match ($period) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => null,
        };

        return $query
            ->whereHas('user', fn ($q) => $q->realUsers())
            ->when($search !== '', fn ($q) => $q->where(fn ($outer) => $outer
                ->whereHas('user', fn ($u) => $u->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', "%{$search}%"))))
            ->when($since, fn ($q) => $q->whereHas('messages', fn ($m) => $m->where('created_at', '>=', $since)));
    }

    /** One inbox tab: unread, awaiting (customer spoke last), replied (we spoke last) or online. */
    public function scopeInboxStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'unread' => $query->whereHas('messages', fn ($q) => $q->where('is_from_admin', false)->where('is_read', false)),
            'awaiting' => $query->whereHas('latestMessage', fn ($q) => $q->where('is_from_admin', false)),
            'replied' => $query->whereHas('latestMessage', fn ($q) => $q->where('is_from_admin', true)),
            'online' => $query->whereHas('user', fn ($q) => $q->where('last_seen_at', '>', now()->subMinutes(2))),
            default => $query,
        };
    }

    /** How many conversations each inbox tab holds for the given search and period. */
    public static function inboxCounts(?string $search = null, ?string $period = null): array
    {
        $counts = [];
        foreach (self::INBOX_STATUSES as $status) {
            $counts[$status] = self::query()->adminInbox($search, $period)->inboxStatus($status)->count();
        }

        return $counts;
    }
}