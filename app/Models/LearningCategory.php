<?php

namespace App\Models;

use App\Models\Concerns\HasLearningSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningCategory extends Model
{
    use HasLearningSlug;
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'icon', 'position', 'created_by'];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = ['position' => 0];

    protected function slugSource(): string
    {
        return 'name';
    }

    // --- Relationships ---------------------------------------------------
    public function courses(): HasMany
    {
        return $this->hasMany(LearningCourse::class)->orderBy('position')->orderBy('title');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(LearningVideo::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(LearningRoom::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
