<?php

namespace App\Models;

use App\Models\Concerns\HasLearningSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LearningVideo extends Model
{
    use HasLearningSlug;
    use SoftDeletes;

    protected $fillable = [
        'learning_category_id', 'learning_course_id', 'learning_topic_id',
        'instructor_id', 'created_by', 'updated_by',
        'title', 'slug', 'description', 'tags', 'visibility', 'status', 'published_at',
        'disk', 'path', 'original_name', 'mime', 'size_bytes', 'duration_seconds',
        'width', 'height', 'thumbnail_path', 'position', 'views',
    ];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = [
        'visibility' => 'course',
        'status' => 'draft',
        'position' => 0,
        'views' => 0,
    ];

    protected $casts = [
        'tags' => 'array',
        'published_at' => 'datetime',
        'size_bytes' => 'integer',
        'duration_seconds' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public const VISIBILITY = [
        'course' => 'Same as course',
        'members' => 'All members (free preview)',
        'private' => 'Private (staff & instructor only)',
    ];

    public const STATUSES = ['draft', 'published'];

    public const QUALITIES = ['360p' => 360, '480p' => 480, '720p' => 720, '1080p' => 1080];

    protected function slugSource(): string
    {
        return 'title';
    }

    // --- Relationships ---------------------------------------------------
    public function category(): BelongsTo
    {
        return $this->belongsTo(LearningCategory::class, 'learning_category_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(LearningTopic::class, 'learning_topic_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function renditions(): HasMany
    {
        return $this->hasMany(LearningVideoRendition::class)->orderByDesc('height');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LearningVideoResource::class)->orderBy('position')->orderBy('id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LearningVideoProgress::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(LearningVideoComment::class);
    }

    // --- Scopes --------------------------------------------------------
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    /**
     * Lessons this user may watch. Keep in step with isVisibleTo() — the
     * policy uses that, list pages use this; FoundationTest asserts they agree.
     * A lesson inside a trashed course only stays visible as a members preview.
     */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasPermission('learning.view')) {
            return $q;
        }

        return $q->where(function (Builder $q) use ($user) {
            $q->where($q->qualifyColumn('instructor_id'), $user->id)
                ->orWhereHas('course', fn (Builder $c) => $c->where($c->qualifyColumn('instructor_id'), $user->id))
                ->orWhere(function (Builder $q) use ($user) {
                    $q->where($q->qualifyColumn('status'), 'published')
                        ->where(function (Builder $q) use ($user) {
                            $q->where($q->qualifyColumn('visibility'), 'members')
                                ->orWhere(function (Builder $q) use ($user) {
                                    $q->where($q->qualifyColumn('visibility'), 'course')
                                        ->where(function (Builder $q) use ($user) {
                                            $q->whereNull($q->qualifyColumn('learning_course_id'))
                                                ->orWhereHas('course', fn (Builder $c) => $c
                                                    ->where($c->qualifyColumn('status'), 'published')
                                                    ->where(fn (Builder $c) => $c
                                                        ->where($c->qualifyColumn('access'), 'open')
                                                        ->orWhereIn($c->qualifyColumn('id'), LearningEnrollment::query()
                                                            ->select('learning_course_id')
                                                            ->where('user_id', $user->id))));
                                        });
                                });
                        });
                });
        });
    }

    // --- Helpers -----------------------------------------------------
    /** Single-record form of scopeVisibleTo() (used by LearningVideoPolicy::view). */
    public function isVisibleTo(User $user): bool
    {
        if ($this->trashed()) {
            return false;
        }

        if ($user->hasPermission('learning.view') || $this->isOwnedBy($user)) {
            return true;
        }

        $course = $this->liveCourse();

        if ($course?->isInstructedBy($user)) {
            return true;
        }

        if (! $this->isPublished()) {
            return false;
        }

        return match ($this->visibility) {
            'members' => true,
            'course' => $this->learning_course_id === null
                || ($course !== null && $course->isPublished() && ($course->isOpen() || $course->isEnrolled($user))),
            default => false,
        };
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->instructor_id !== null && (int) $this->instructor_id === (int) $user->id;
    }

    /** The parent course unless there is none or it sits in the trash. */
    private function liveCourse(): ?LearningCourse
    {
        if ($this->learning_course_id === null) {
            return null;
        }

        $course = $this->course;

        return $course && ! $course->trashed() ? $course : null;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function hasFile(): bool
    {
        return $this->path !== null;
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path
            ? Storage::disk(config('learning.public_disk'))->url($this->thumbnail_path)
            : null;
    }

    public function durationLabel(): string
    {
        if ($this->duration_seconds === null) {
            return '—';
        }

        $s = max(0, (int) $this->duration_seconds);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $sec = $s % 60;

        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $sec)
            : sprintf('%d:%02d', $m, $sec);
    }

    public function descriptionHtml(): string
    {
        return $this->description
            ? Str::markdown($this->description, ['html_input' => 'escape', 'allow_unsafe_links' => false])
            : '';
    }

    public function sourceQualityLabel(): string
    {
        $height = (int) $this->height;

        foreach (array_reverse(self::QUALITIES, true) as $label => $min) {
            if ($height >= $min) {
                return $label;
            }
        }

        return 'Original';
    }

    public function sizeLabel(): string
    {
        return static::humanBytes($this->size_bytes);
    }

    public function progressFor(?User $user): ?LearningVideoProgress
    {
        if (! $user || ! $this->exists) {
            return null;
        }

        if ($this->relationLoaded('progress')) {
            return $this->progress->firstWhere('user_id', $user->id);
        }

        return $this->progress()->where('user_id', $user->id)->first();
    }

    /** "1.2 GB"-style size, shared by every learning file model. */
    public static function humanBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) max(0, $bytes);
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return $i === 0
            ? $bytes.' B'
            : rtrim(rtrim(number_format($size, 1), '0'), '.').' '.$units[$i];
    }
}
