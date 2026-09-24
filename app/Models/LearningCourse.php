<?php

namespace App\Models;

use App\Models\Concerns\HasLearningSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LearningCourse extends Model
{
    use HasLearningSlug;
    use SoftDeletes;

    protected $fillable = [
        'learning_category_id', 'title', 'slug', 'summary', 'description',
        'thumbnail_path', 'level', 'instructor_id', 'status', 'access',
        'position', 'published_at', 'created_by', 'updated_by',
    ];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = [
        'status' => 'draft',
        'access' => 'open',
        'position' => 0,
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public const STATUSES = ['draft', 'published'];

    public const ACCESS = [
        'open' => 'Open to all members',
        'enrolled' => 'Enrolled learners only',
    ];

    public const LEVELS = [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
    ];

    protected function slugSource(): string
    {
        return 'title';
    }

    // --- Relationships ---------------------------------------------------
    public function category(): BelongsTo
    {
        return $this->belongsTo(LearningCategory::class, 'learning_category_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(LearningTopic::class)->orderBy('position')->orderBy('id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(LearningVideo::class)->orderBy('position')->orderBy('id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(LearningEnrollment::class);
    }

    public function enrolledUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'learning_enrollments', 'learning_course_id', 'user_id')->withTimestamps();
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(LearningRoom::class);
    }

    // --- Scopes --------------------------------------------------------
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    /** Courses (not trashed) the user holds an enrolment in. */
    public function scopeEnrolledBy(Builder $q, User $user): Builder
    {
        return $q->whereIn($q->qualifyColumn('id'), LearningEnrollment::query()
            ->select('learning_course_id')
            ->where('user_id', $user->id));
    }

    /**
     * Courses this user may open. Keep in step with isVisibleTo() — the
     * policy uses that, list pages use this; FoundationTest asserts they agree.
     */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasPermission('learning.view')) {
            return $q;
        }

        return $q->where(function (Builder $q) use ($user) {
            $q->where($q->qualifyColumn('instructor_id'), $user->id)
                ->orWhere(function (Builder $q) use ($user) {
                    $q->where($q->qualifyColumn('status'), 'published')
                        ->where(function (Builder $q) use ($user) {
                            $q->where($q->qualifyColumn('access'), 'open')
                                ->orWhereIn($q->qualifyColumn('id'), LearningEnrollment::query()
                                    ->select('learning_course_id')
                                    ->where('user_id', $user->id));
                        });
                });
        });
    }

    // --- Helpers -----------------------------------------------------
    /** Single-record form of scopeVisibleTo() (used by LearningCoursePolicy::view). */
    public function isVisibleTo(User $user): bool
    {
        if ($this->trashed()) {
            return false;
        }

        if ($user->hasPermission('learning.view') || $this->isInstructedBy($user)) {
            return true;
        }

        return $this->isPublished() && ($this->isOpen() || $this->isEnrolled($user));
    }

    public function isInstructedBy(?User $user): bool
    {
        return $user !== null && $this->instructor_id !== null && (int) $this->instructor_id === (int) $user->id;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isOpen(): bool
    {
        return $this->access === 'open';
    }

    public function isEnrolled(?User $user): bool
    {
        if (! $user || ! $this->exists) {
            return false;
        }

        return $this->enrollments()->where('user_id', $user->id)->exists();
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path
            ? Storage::disk(config('learning.public_disk'))->url($this->thumbnail_path)
            : null;
    }

    public function descriptionHtml(): string
    {
        return $this->description
            ? Str::markdown($this->description, ['html_input' => 'escape', 'allow_unsafe_links' => false])
            : '';
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? 'All levels';
    }

    public function accessLabel(): string
    {
        return self::ACCESS[$this->access] ?? ucfirst((string) $this->access);
    }
}
