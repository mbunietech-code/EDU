<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimizationRecommendation extends Model
{
    protected $fillable = [
        'scan_id',
        'category',
        'operation',
        'table_name',
        'column_name',
        'affected_count',
        'expiration_status',
        'action',
        'risk_level',
        'estimated_recovery_mb',
        'where_sql',
        'where_bindings',
        'update_values',
        'index_sql',
        'sql_preview',
        'rollback_note',
        'status',
        'reviewed_by',
        'reviewed_at',
        'executed_by',
        'executed_at',
        'executed_count',
        'backup_path',
        'rejection_reason',
    ];

    protected $casts = [
        'where_bindings' => 'array',
        'update_values' => 'array',
        'reviewed_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(OptimizationScan::class, 'scan_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /**
     * Whether this recommendation can be run directly from the admin UI —
     * only idempotent, well-scoped delete/update/create_index operations
     * with an approval already on record. Orphan/slow-query findings are
     * detection-only and always require manual investigation.
     */
    public function isExecutable(): bool
    {
        return $this->status === 'approved'
            && in_array($this->operation, ['delete', 'update', 'create_index'], true);
    }
}
