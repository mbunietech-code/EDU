<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a research work request's history: created, stage moved,
 * quoted, files delivered, revision asked… `note` is shown to the customer,
 * `internal_note` and is_internal events are staff only.
 */
class ResearchRequestEvent extends Model
{
    public const LABELS = [
        'created' => 'Request sent',
        'stage' => 'Stage changed',
        'progress' => 'Progress updated',
        'assigned' => 'Assigned',
        'quote_sent' => 'Price quoted',
        'quote_accepted' => 'Quote accepted',
        'quote_declined' => 'Quote declined',
        'files' => 'Files delivered',
        'revision' => 'Revision requested',
        'details' => 'Details changed',
        'cancelled' => 'Cancelled',
        'completed' => 'Accepted & closed',
        'reopened' => 'Reopened',
        'note' => 'Internal note',
    ];

    protected $fillable = [
        'research_request_id', 'user_id', 'type', 'from_stage_id', 'to_stage_id',
        'progress', 'note', 'internal_note', 'is_internal', 'meta',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'progress' => 'integer',
        'meta' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ResearchRequest::class, 'research_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(ResearchRequestStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(ResearchRequestStage::class, 'to_stage_id');
    }

    public function scopeForCustomer(Builder $q): Builder
    {
        return $q->where('is_internal', false);
    }

    public function label(): string
    {
        if ($this->type === 'stage' && $this->toStage) {
            return 'Moved to '.$this->toStage->name;
        }

        return self::LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
