<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchReadingProgress extends Model
{
    protected $table = 'research_reading_progress';

    protected $fillable = [
        'user_id', 'research_id', 'last_section_id', 'percent',
        'done_section_ids', 'last_read_at',
    ];

    protected $casts = [
        'done_section_ids' => 'array',
        'last_read_at' => 'datetime',
    ];

    public function research(): BelongsTo
    {
        return $this->belongsTo(Research::class);
    }

    public function lastSection(): BelongsTo
    {
        return $this->belongsTo(ResearchSection::class, 'last_section_id');
    }
}
