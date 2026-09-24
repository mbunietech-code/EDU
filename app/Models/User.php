<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Support\Permissions;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
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
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'permissions' => 'array',
            'can_write_research' => 'boolean',
            'can_teach' => 'boolean',
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
     * This user's own internal team-chat thread — only meaningful for a
     * non-super-admin user (see AdminConversation).
     */
    public function adminConversation(): HasOne
    {
        return $this->hasOne(AdminConversation::class, 'admin_id');
    }

    public function unreadTeamChatMessagesCount(): int
    {
        if (! $this->is_admin) {
            return 0;
        }

        return $this->unreadDirectTeamChatCount() + $this->unreadGroupChatCount();
    }

    protected function unreadGroupChatCount(): int
    {
        // Same guard as the direct count: missing group tables must never break every admin page.
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

    protected function unreadDirectTeamChatCount(): int
    {
        // Guards against the admin panel going down on a server where the
        // code was deployed before the team-chat migration/alter has run —
        // a missing-table error here must never take out every admin page,
        // since this is called from the shared admin sidebar on all of them.
        try {
            if ($this->isSuperAdmin()) {
                return AdminMessage::where('sender_id', '!=', $this->id)
                    ->where('is_read', false)
                    ->count();
            }

            return AdminMessage::whereHas('conversation', fn ($query) => $query->where('admin_id', $this->id))
                ->where('sender_id', '!=', $this->id)
                ->where('is_read', false)
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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(2));
    }

    public static function anyAdminOnline(): bool
    {
        return static::where('is_admin', true)
            ->where('last_seen_at', '>', now()->subMinutes(2))
            ->exists();
    }
}
