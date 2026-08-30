<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletedRecord extends Model
{
    protected $fillable = [
        'entity',
        'entity_id',
        'label',
        'reason',
        'snapshot',
        'deleted_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
