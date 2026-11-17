<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A support admin's computer or phone that rings for video help calls, even
 * while nobody is signed in on it. Registered once while signed in; removed
 * from the Video help page. The token (push endpoint / FCM token) is the
 * device's only secret, so it is looked up by its sha256 hash.
 */
class CallDevice extends Model
{
    public const WEBPUSH = 'webpush';

    public const FCM = 'fcm';

    protected $fillable = [
        'user_id', 'channel', 'name', 'platform', 'token', 'token_hash',
        'test_until', 'last_rung_at', 'last_seen_at',
    ];

    protected $hidden = ['token'];

    protected $casts = [
        'test_until' => 'datetime',
        'last_rung_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token, ?string $channel = null): ?self
    {
        return static::query()
            ->where('token_hash', static::hashToken($token))
            ->when($channel, fn ($q) => $q->where('channel', $channel))
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kindLabel(): string
    {
        return $this->channel === self::FCM ? 'MHub app' : 'Browser';
    }
}
