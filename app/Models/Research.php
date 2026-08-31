<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Research extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'researches';

    protected $fillable = [
        'research_category_id', 'user_id', 'title', 'slug', 'summary',
        'cover_image', 'status', 'review_note', 'reviewed_by',
        'submitted_at', 'published_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public const STATUSES = [
        'draft', 'submitted', 'under_review', 'changes_requested',
        'approved', 'published', 'archived',
    ];

    /** Statuses an author may still edit the content in. */
    public const EDITABLE = ['draft', 'changes_requested'];

    protected static function booted(): void
    {
        static::saving(function (self $r) {
            if (empty($r->slug)) {
                $r->slug = static::uniqueSlug($r->title);
            }
        });
    }

    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::random(8);
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    // --- Relationships ---------------------------------------------------
    public function category(): BelongsTo
    {
        return $this->belongsTo(ResearchCategory::class, 'research_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(ResearchChapter::class)->orderBy('position')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ResearchReview::class)->latest();
    }

    // --- Scopes --------------------------------------------------------
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeInReview(Builder $q): Builder
    {
        return $q->whereIn('status', ['submitted', 'under_review', 'changes_requested']);
    }

    /**
     * Count of research awaiting review — safe to call before the table exists
     * (used for the admin sidebar badge, which renders on every admin page).
     */
    public static function pendingReviewCount(): int
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('researches')) {
                return 0;
            }

            return static::inReview()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    // --- Helpers -----------------------------------------------------
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isEditableByAuthor(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this->status, ['draft', 'changes_requested'], true)
            && $this->chapters()->exists();
    }

    public function statusLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    public function sectionsCount(): int
    {
        return ResearchSection::whereIn('research_chapter_id', $this->chapters()->select('id'))->count();
    }

    /** Ordered flat list of every section across every chapter (for prev/next). */
    public function flatSections()
    {
        return ResearchSection::query()
            ->join('research_chapters', 'research_chapters.id', '=', 'research_sections.research_chapter_id')
            ->where('research_chapters.research_id', $this->id)
            ->orderBy('research_chapters.position')
            ->orderBy('research_chapters.id')
            ->orderBy('research_sections.position')
            ->orderBy('research_sections.id')
            ->select('research_sections.*')
            ->get();
    }
}
