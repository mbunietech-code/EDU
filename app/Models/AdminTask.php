<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Work an admin gives to a team member (picked by name and job title). */
class AdminTask extends Model
{
    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public const STATUSES = ['todo' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done', 'cancelled' => 'Cancelled'];

    public const OPEN = ['todo', 'in_progress'];

    protected $fillable = ['title', 'description', 'priority', 'status', 'due_at', 'assigned_to', 'created_by', 'completed_at'];

    protected $casts = [
        'due_at' => 'date',
        'completed_at' => 'datetime',
        'reminded_on' => 'date',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AdminTaskComment::class)->oldest();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at !== null && $this->due_at->lt(Carbon::today());
    }

    /** Someone who gave or got the task. */
    public function involves(User $user): bool
    {
        return (int) $this->assigned_to === (int) $user->id || (int) $this->created_by === (int) $user->id;
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN);
    }

    /** Tasks this user may see: theirs (given or got), or all with tasks.manage. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->can('tasks.manage')) {
            return $q;
        }

        return $q->where(fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id));
    }
}
