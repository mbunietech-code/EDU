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

    /** See it and comment: who gave or got it, or a tasks manager. */
    public function canBeSeenBy(User $user): bool
    {
        return $this->involves($user) || $user->can('tasks.manage');
    }

    /** Edit / delete / re-assign: who gave it, or a tasks manager. */
    public function canBeEditedBy(User $user): bool
    {
        return (int) $this->created_by === (int) $user->id || $user->can('tasks.manage');
    }

    /** Change the status: the person who got it, who gave it, or a tasks manager. */
    public function canBeMovedBy(User $user): bool
    {
        return $this->involves($user) || $user->can('tasks.manage');
    }

    /** Tell the person it was given to, unless they gave it to themselves. */
    public function notifyAssignee(User $by): void
    {
        $this->load('assignee');

        if ($this->assignee && (int) $this->assignee->id !== (int) $by->id) {
            $this->assignee->notify(new \App\Notifications\Admin\TaskAssigned($this, $by->name));
        }
    }

    /** Tell whoever gave / got the task, except the person who just acted. */
    public function notifyOthers(User $by, string $message): void
    {
        collect([$this->assigned_to, $this->created_by])
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) $by->id)
            ->each(fn ($id) => User::query()->find($id)?->notify(new \App\Notifications\Admin\TaskUpdated($this, $message)));
    }

    /** Admins a task can be given to, by job title then name. */
    public static function people()
    {
        return User::query()->where('is_admin', true)->where('is_guest', false)->where('status', 'active')
            ->orderByRaw('job_title IS NULL')->orderBy('job_title')->orderBy('name')
            ->get(['id', 'name', 'job_title']);
    }

    /** Tasks list ordering: in progress, then urgent, then soonest due. */
    public function scopeWorkOrder(Builder $q): Builder
    {
        return $q->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'todo' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->latest('id');
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
