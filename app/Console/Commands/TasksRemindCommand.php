<?php

namespace App\Console\Commands;

use App\Models\AdminTask;
use App\Notifications\Admin\TaskDueReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Reminds the person who got an open task the day before it is due, on the
 * day, and once after it became overdue (reminded_on).
 */
class TasksRemindCommand extends Command
{
    protected $signature = 'tasks:remind';

    protected $description = 'Remind admins about tasks that are due or overdue';

    public function handle(): int
    {
        $today = Carbon::today();
        $sent = 0;

        AdminTask::query()->open()->with('assignee')
            ->whereNotNull('assigned_to')
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<=', $today->copy()->addDay())
            ->each(function (AdminTask $task) use ($today, &$sent) {
                $days = (int) $today->diffInDays($task->due_at, false);

                $when = match (true) {
                    $days === 1 => 'due tomorrow',
                    $days === 0 => 'due today',
                    default => 'overdue',
                };

                $already = $days >= 0
                    ? $task->reminded_on?->isSameDay($today)
                    : ($task->reminded_on !== null && $task->reminded_on->gt($task->due_at));

                if ($already || ! $task->assignee || $task->assignee->status !== 'active') {
                    return;
                }

                $task->assignee->notify(new TaskDueReminder($task, $when));
                $task->forceFill(['reminded_on' => $today])->save();
                $sent++;
            });

        $this->info("Reminders sent for {$sent} task(s).");

        return self::SUCCESS;
    }
}
