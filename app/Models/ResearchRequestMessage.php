<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A message on a research work request: customer ↔ team. Internal notes
 * (is_internal) are staff only and are never shown or sent to the customer.
 */
class ResearchRequestMessage extends Model
{
    protected $fillable = ['research_request_id', 'user_id', 'is_from_admin', 'is_internal', 'body'];

    protected $casts = [
        'is_from_admin' => 'boolean',
        'is_internal' => 'boolean',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ResearchRequest::class, 'research_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ResearchRequestFile::class, 'message_id')->oldest('id');
    }

    public function scopeForCustomer(Builder $q): Builder
    {
        return $q->where('is_internal', false);
    }
}
