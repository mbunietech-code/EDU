<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchChapter extends Model
{
    protected $fillable = ['research_id', 'title', 'position'];

    public function research(): BelongsTo
    {
        return $this->belongsTo(Research::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ResearchSection::class)->orderBy('position')->orderBy('id');
    }
}
