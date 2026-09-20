<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptimizationScan extends Model
{
    protected $fillable = [
        'triggered_by',
        'total_tables',
        'total_rows',
        'total_size_mb',
        'expired_count',
        'expiring_soon_count',
        'inactive_count',
        'duplicate_count',
        'missing_index_count',
        'slow_query_count',
        'orphaned_count',
        'estimated_recovery_mb',
        'notes',
    ];

    protected $casts = [
        'notes' => 'array',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(OptimizationRecommendation::class, 'scan_id');
    }
}
