<?php

namespace App\Models;

use App\Models\Concerns\HasLearningSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LearningRoom extends Model
{
    use HasLearningSlug;
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'description',
        'learning_category_id', 'learning_course_id', 'learning_topic_id',
        'host_id', 'created_by', 'updated_by', 'status', 'access',
        'scheduled_at', 'duration_minutes', 'provider_room',
        'chat_enabled', 'questions_enabled', 'allow_participant_media',
        'allow_screen_share', 'is_locked',
        'reminder_sent_at', 'started_at', 'ended_at', 'cancel_reason',
    ];

    /** Mirrors the column defaults so unsaved / just-created models read the same as reloaded ones. */
    protected $attributes = [
        'status' => 'draft',
        'access' => 'public',
        'duration_minutes' => 60,
        'chat_enabled' => true,
        'questions_enabled' => true,
        'allow_participant_media' => true,
        'allow_screen_share' => false,
        'is_locked' => false,
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'chat_enabled' => 'boolean',
        'questions_enabled' => 'boolean',
        'allow_participant_media' => 'boolean',
        'allow_screen_share' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public const STATUSES = ['draft', 'scheduled', 'live', 'completed', 'cancelled'];

    public const ACCESS = [
        'public' => 'All members',
        'private' => 'Invited members only',
        'category' => 'Learners enrolled in the category',
        'course' => 'Learners enrolled in the course',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $room) {
            if (empty($room->provider_room)) {
                $room->provider_room = static::uniqueProviderRoom();
            }
        });
    }

    protected function slugSource(): string
    {
        return 'title';
    }

    /** Unguessable conference name — it is the only thing standing between a link and the call. */
    public static function uniqueProviderRoom(): string
    {
        do {
            $name = config('learning.live.room_prefix').'-'.Str::lower(Str::random(20));
        } while (static::withTrashed()->where('provider_room', $name)->exists());

        return $name;
    }

    /**
     * Live rooms this user can see — cached briefly because it feeds the
     * sidebar badge on every page. Safe before the table exists.
     */
    public static function liveCountFor(User $user): int
    {
        try {
            return (int) Cache::remember('learning.live_count.'.$user->id, 30, function () use ($user) {
                if (! Schema::hasTable('learning_rooms')) {
                    return 0;
                }

                return static::live()->visibleTo($user)->count();
            });
        } catch (\Throwable) {
            return 0;
        }
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

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(LearningRoomMember::class);
    }

    public function memberUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'learning_room_members', 'learning_room_id', 'user_id')->withTimestamps();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LearningRoomSession::class)->orderByDesc('started_at');
    }

    /** The session still running (at most one — RoomService::start() locks the room). */
    public function currentSession(): HasOne
    {
        return $this->hasOne(LearningRoomSession::class)
            ->ofMany(['id' => 'max'], fn (Builder $q) => $q->whereNull('ended_at'));
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LearningRoomAttendance::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LearningRoomMessage::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(LearningRoomRecording::class)->latest();
    }

    /** Files the host shares in the classroom (slides, worksheets, …). */
    public function materials(): HasMany
    {
        return $this->hasMany(LearningRoomMaterial::class)->latest();
    }

    // --- Scopes --------------------------------------------------------
    /**
     * Rooms this user may see. Keep in step with isVisibleTo() — the policy
     * uses that, list pages use this; FoundationTest asserts they agree.
     */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasPermission('rooms.view')) {
            return $q;
        }

        return $q->where(function (Builder $q) use ($user) {
            $q->where($q->qualifyColumn('host_id'), $user->id)
                ->orWhere(function (Builder $q) use ($user) {
                    $q->where($q->qualifyColumn('status'), '!=', 'draft')
                        ->where(function (Builder $q) use ($user) {
                            $q->where($q->qualifyColumn('access'), 'public')
                                ->orWhere(fn (Builder $q) => $q
                                    ->where($q->qualifyColumn('access'), 'private')
                                    ->whereIn($q->qualifyColumn('id'), LearningRoomMember::query()
                                        ->select('learning_room_id')
                                        ->where('user_id', $user->id)))
                                ->orWhere(fn (Builder $q) => $q
                                    ->where($q->qualifyColumn('access'), 'course')
                                    ->whereIn($q->qualifyColumn('learning_course_id'), LearningCourse::query()
                                        ->enrolledBy($user)
                                        ->select('id')))
                                ->orWhere(fn (Builder $q) => $q
                                    ->where($q->qualifyColumn('access'), 'category')
                                    ->whereIn($q->qualifyColumn('learning_category_id'), LearningCourse::query()
                                        ->enrolledBy($user)
                                        ->select('learning_category_id')));
                        });
                });
        });
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->where('status', 'live');
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where('status', 'scheduled')->orderBy('scheduled_at');
    }

    public function scopeCompleted(Builder $q): Builder
    {
        return $q->where('status', 'completed');
    }

    // --- Helpers -----------------------------------------------------
    /** Single-record form of scopeVisibleTo() (used by LearningRoomPolicy::view). */
    public function isVisibleTo(User $user): bool
    {
        if ($this->trashed()) {
            return false;
        }

        if ($user->hasPermission('rooms.view') || $this->isHostedBy($user)) {
            return true;
        }

        if ($this->isDraft()) {
            return false;
        }

        return match ($this->access) {
            'public' => true,
            'private' => $this->members()->where('user_id', $user->id)->exists(),
            'course' => $this->learning_course_id !== null
                && LearningCourse::query()->enrolledBy($user)->whereKey($this->learning_course_id)->exists(),
            'category' => $this->learning_category_id !== null
                && LearningCourse::query()->enrolledBy($user)->where('learning_category_id', $this->learning_category_id)->exists(),
            default => false,
        };
    }

    /** Same rule as LearningRoomPolicy::manage(). */
    public function isManageableBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasPermission('rooms.manage')
            || ($this->isHostedBy($user) && $user->canHostRooms());
    }

    public function isHostedBy(?User $user): bool
    {
        return $user !== null && $this->host_id !== null && (int) $this->host_id === (int) $user->id;
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function endsAt(): ?Carbon
    {
        return $this->scheduled_at?->copy()->addMinutes((int) $this->duration_minutes);
    }

    public function statusLabel(): string
    {
        return ucfirst((string) $this->status);
    }

    public function accessLabel(): string
    {
        return self::ACCESS[$this->access] ?? ucfirst((string) $this->access);
    }
}
