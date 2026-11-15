<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    public const STREAMING = 'streaming';

    public const DONE = 'done';

    public const STOPPED = 'stopped';

    public const ERROR = 'error';

    /** A reply still "streaming" with no write for this long lost its request (timeout). */
    public const STALE_SECONDS = 200;

    protected $fillable = ['conversation_id', 'role', 'content', 'tools_used', 'status', 'meta'];

    protected $casts = [
        'tools_used' => 'array',
        'meta' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }

    public function isStreaming(): bool
    {
        return $this->status === self::STREAMING;
    }

    /** What the page / app needs to draw one bubble. */
    public function toChatArray(): array
    {
        $status = $this->status ?? self::DONE;

        if ($status === self::STREAMING && $this->updated_at && $this->updated_at->lt(now()->subSeconds(self::STALE_SECONDS))) {
            $status = self::ERROR;
        }

        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => (string) $this->content,
            'status' => $status,
            'tools_used' => $this->tools_used,
            'activity' => $status === self::STREAMING ? ($this->meta['activity'] ?? null) : null,
            'model' => $this->meta['model'] ?? null,
            'time' => $this->created_at?->format('H:i'),
        ];
    }
}
