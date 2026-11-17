<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One step of the research work process (Received → … → Closed). Admins can
 * add, rename, recolour and reorder stages; the system ones (with a `key`)
 * are the stages the workflow moves to by itself and cannot be deleted.
 */
class ResearchRequestStage extends Model
{
    public const RECEIVED = 'received';

    public const QUOTED = 'quoted';

    public const IN_PROGRESS = 'in_progress';

    public const DRAFT_READY = 'draft_ready';

    public const REVISIONS = 'revisions';

    public const DELIVERED = 'delivered';

    public const CLOSED = 'closed';

    /**
     * Flat colours a stage can use (badge, board column, tracker dot). No
     * violet / pink "AI look" accents — the site style is flat and plain.
     */
    public const COLORS = ['gray', 'sky', 'indigo', 'teal', 'amber', 'orange', 'emerald', 'green', 'red'];

    /** Hex for the app (MHub) — the Tailwind 600 shade of each colour. */
    public const HEX = [
        'gray' => '#4B5563', 'sky' => '#0284C7', 'indigo' => '#4F46E5', 'teal' => '#0D9488',
        'amber' => '#D97706', 'orange' => '#EA580C', 'emerald' => '#059669', 'green' => '#16A34A',
        'red' => '#DC2626',
    ];

    protected $fillable = ['key', 'name', 'description', 'color', 'progress', 'sort_order', 'allows_cancel', 'is_active'];

    protected $casts = [
        'progress' => 'integer',
        'sort_order' => 'integer',
        'allows_cancel' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(ResearchRequest::class, 'stage_id');
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public function isSystem(): bool
    {
        return $this->key !== null;
    }

    public function isClosed(): bool
    {
        return $this->key === self::CLOSED;
    }

    public function hex(): string
    {
        return self::HEX[$this->color] ?? self::HEX['indigo'];
    }

    /** All stages in process order (active ones only unless asked). */
    public static function flow(bool $withInactive = false): Collection
    {
        return static::query()
            ->when(! $withInactive, fn (Builder $q) => $q->where('is_active', true))
            ->ordered()
            ->get();
    }

    public static function byKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }

    /** The stage a new request starts in: "Received", else the first active one. */
    public static function starting(): ?self
    {
        return static::byKey(self::RECEIVED) ?? static::query()->where('is_active', true)->ordered()->first();
    }

    /**
     * Inline badge style for the web (colours are chosen at run time, so
     * Tailwind cannot see them as classes): a light tint, coloured text and ring.
     */
    public function badgeStyle(): string
    {
        $hex = $this->hex();

        return "background-color: {$hex}14; color: {$hex}; box-shadow: inset 0 0 0 1px {$hex}33;";
    }
}
