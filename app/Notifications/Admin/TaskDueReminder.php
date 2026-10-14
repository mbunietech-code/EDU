<?php

namespace App\Notifications\Admin;

use App\Models\AdminTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A task of yours is due tomorrow, today, or overdue. */
class TaskDueReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AdminTask $task, public string $when)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'message' => 'Task '.$this->when.': '.$this->task->title,
            'url' => route('admin.tasks.show', $this->task),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Task '.$this->when.': '.$this->task->title)
            ->line('Your task "'.$this->task->title.'" is '.$this->when.'.')
            ->action('Open the task', route('admin.tasks.show', $this->task));
    }
}
