<?php

namespace App\Notifications\Admin;

use App\Models\AdminTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Something happened on a task you gave or got: a new status or a comment. */
class TaskUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AdminTask $task, public string $message)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'message' => $this->message,
            'url' => route('admin.tasks.show', $this->task),
        ];
    }
}
