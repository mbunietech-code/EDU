<?php

namespace App\Models;

use App\Notifications\Auth\EmailVerificationCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'role',
        'permissions',
        'status',
        'can_write_research',
        'can_teach',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_code_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'permissions' => 'array',
            'can_write_research' => 'boolean',
            'can_teach' => 'boolean',
            'is_guest' => 'boolean',
            'guest_admitted_at' => 'datetime',
            'guest_denied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep the legacy is_admin flag in step with the role.
        static::saving(function (User $user) {
            if ($user->isDirty('role') && $user->role !== null) {
                $user->is_admin = in_array($user->role, [
                    Permissions::ROLE_ADMIN,
                    Permissions::ROLE_SUPER_ADMIN,
                ], true);
            }
        });
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->sendEmailVerificationCode();
    }

    public function sendEmailVerificationCode(): void
    {
        $code = (string) random_int(100000, 999999);
        $expiresInMinutes = 15;

        if ($this->emailVerificationCodeColumnsExist()) {
            $this->forceFill([
                'email_verification_code' => $code,
                'email_verification_code_expires_at' => now()->addMinutes($expiresInMinutes),
            ])->save();
        } else {
            Cache::put($this->emailVerificationCodeCacheKey(), $code, now()->addMinutes($expiresInMinutes));
        }

        $this->notify(new EmailVerificationCode($code, $expiresInMinutes));
    }

    public function hasValidEmailVerificationCode(string $code): bool
    {
        $code = preg_replace('/\D+/', '', $code);

        if ($this->emailVerificationCodeColumnsExist()) {
            return $this->email_verification_code !== null
                && hash_equals($this->email_verification_code, $code)
                && $this->email_verification_code_expires_at?->isFuture();
        }

        $cachedCode = Cache::get($this->emailVerificationCodeCacheKey());

        return is_string($cachedCode) && hash_equals($cachedCode, $code);
    }

    public function clearEmailVerificationCode(): void
    {
        if ($this->emailVerificationCodeColumnsExist()) {
            $this->forceFill([
                'email_verification_code' => null,
                'email_verification_code_expires_at' => null,
            ])->save();

            return;
        }

        Cache::forget($this->emailVerificationCodeCacheKey());
    }

    public function needsFreshEmailVerificationCode(): bool
    {
        if ($this->emailVerificationCodeColumnsExist()) {
            return $this->email_verification_code === null
                || $this->email_verification_code_expires_at?->isPast();
        }

        return ! Cache::has($this->emailVerificationCodeCacheKey());
    }

    protected function emailVerificationCodeCacheKey(): string
    {
        return 'email-verification-code:' . $this->getKey() . ':' . sha1((string) $this->email);
    }

    protected function emailVerificationCodeColumnsExist(): bool
    {
        return Schema::hasColumn($this->getTable(), 'email_verification_code')
            && Schema::hasColumn($this->getTable(), 'email_verification_code_expires_at');
    }

    /**
     * Effective role. Falls back to the is_admin flag while the `role`
     * column has not been added yet (pre-migration safety).
     */
    public function roleName(): string
    {
        if (! empty($this->role)) {
            return $this->role;
        }

        return $this->is_admin ? Permissions::ROLE_ADMIN : Permissions::ROLE_USER;
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleName() === Permissions::ROLE_SUPER_ADMIN
            // Before the role column exists, every admin acts as super admin
            // so nobody is locked out during the upgrade.
            || (empty($this->role) && (bool) $this->is_admin);
    }

    /**
     * Does this user hold a specific admin permission?
     *
     * A `permissions` value of null means "unrestricted" — existing admins
     * keep full access until a super admin explicitly narrows them. An
     * array (even empty) means the admin is restricted to exactly those keys.
     */
    public function hasPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->is_admin) {
            return false;
        }

        if ($this->permissions === null) {
            return true;
        }

        return in_array($key, $this->permissions, true);
    }

    /**
     * True when this admin has an explicit (narrowed) permission list.
     */
    public function isRestrictedAdmin(): bool
    {
        return $this->is_admin && ! $this->isSuperAdmin() && is_array($this->permissions);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function researches(): HasMany
    {
        return $this->hasMany(Research::class);
    }

    /** May this user author research content (admin-granted)? */
    public function canWriteResearch(): bool
    {
        return (bool) $this->can_write_research || $this->is_admin;
    }

    // --- Learning (ROOM) ------------------------------------------------
    /** Admin-granted instructor flag (users.can_teach). */
    public function isInstructor(): bool
    {
        return (bool) $this->can_teach;
    }

    public function canHostRooms(): bool
    {
        return $this->isInstructor() || $this->hasPermission('rooms.manage');
    }

    public function canUploadLessons(): bool
    {
        return $this->isInstructor() || $this->hasPermission('learning.manage');
    }

    /** May open the Teaching Studio (hosting, uploading, or staff oversight). */
    public function canAccessStudio(): bool
    {
        return $this->canHostRooms()
            || $this->canUploadLessons()
            || $this->hasPermission('rooms.view')
            || $this->hasPermission('learning.view');
    }

    public function learningEnrollments(): HasMany
    {
        return $this->hasMany(LearningEnrollment::class);
    }

    public function learningProgress(): HasMany
    {
        return $this->hasMany(LearningVideoProgress::class);
    }

    public function hostedRooms(): HasMany
    {
        return $this->hasMany(LearningRoom::class, 'host_id');
    }

    public function teachingVideos(): HasMany
    {
        return $this->hasMany(LearningVideo::class, 'instructor_id');
    }

    public function taughtCourses(): HasMany
    {
        return $this->hasMany(LearningCourse::class, 'instructor_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * Unread Team Chat messages — private chats and groups alike, since both
     * are admin groups the user is a member of.
     */
    public function unreadTeamChatMessagesCount(): int
    {
        if (! $this->is_admin) {
            return 0;
        }

        // Called from the shared admin sidebar on every page: missing chat
        // tables (code deployed before the alter ran) must never break it.
        try {
            return (int) \App\Models\AdminGroupMessage::query()
                ->whereIn('admin_group_id', fn ($q) => $q->select('admin_group_id')->from('admin_group_members')->where('user_id', $this->id))
                ->where('sender_id', '!=', $this->id)
                ->where('is_deleted', false)
                ->whereRaw('admin_group_messages.id > COALESCE((SELECT m.last_read_message_id FROM admin_group_members m WHERE m.admin_group_id = admin_group_messages.admin_group_id AND m.user_id = ?), 0)', [$this->id])
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function unreadChatMessagesCount(): int
    {
        if ($this->is_admin) {
            return ChatMessage::where('is_from_admin', false)
                ->where('is_read', false)
                ->count();
        }

        return ChatMessage::whereHas('conversation', fn ($query) => $query->where('user_id', $this->id))
            ->where('is_from_admin', true)
            ->where('is_read', false)
            ->count();
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * A meeting guest: joined one room through its guest link with just a
     * name. Restricted to that room (RestrictGuests); never emailed.
     */
    public function isGuest(): bool
    {
        return (bool) $this->is_guest;
    }

    /** Guests have made-up addresses: the mail channel skips them. */
    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->isGuest() ? null : $this->email;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(2));
    }

    /**
     * Customer/member accounts only. Meeting guests use short-lived access and
     * should not affect admin user counts, member pickers or customer reports.
     */
    public function scopeRealUsers(Builder $query): Builder
    {
        if (Schema::hasColumn($this->getTable(), 'is_guest')) {
            $query->where(function (Builder $q) {
                $q->where('is_guest', false)->orWhereNull('is_guest');
            });
        }

        return $query;
    }

    public static function anyAdminOnline(): bool
    {
        return static::where('is_admin', true)
            ->where('last_seen_at', '>', now()->subMinutes(2))
            ->exists();
    }
}
