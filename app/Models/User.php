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
