<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorLog extends Model
{
    protected $fillable = [
        'fingerprint', 'level', 'exception', 'message', 'file', 'line',
        'url', 'method', 'user_id', 'ip', 'trace', 'context',
        'occurrences', 'first_seen_at', 'last_seen_at', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'context' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Unresolved count, safe to call before the table exists.
     */
    public static function openCount(): int
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('error_logs')) {
                return 0;
            }

            return static::whereNull('resolved_at')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Short "ClassName" without namespace. */
    public function shortException(): string
    {
        return class_basename($this->exception);
    }

    public function location(): string
    {
        return $this->file ? $this->file.($this->line ? ':'.$this->line : '') : '—';
    }
}
