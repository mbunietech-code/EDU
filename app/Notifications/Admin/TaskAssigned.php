<?php

namespace App\Notifications\Admin;

use App\Models\AdminTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** You were given a task (or it was handed to you). */
class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AdminTask $task, public string $byName)
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
            'message' => $this->byName.' gave you a task: '.$this->task->title,
            'url' => route('admin.tasks.show', $this->task),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('New task: '.$this->task->title)
            ->line($this->byName.' gave you a task.')
            ->line('Task: '.$this->task->title)
            ->line('Priority: '.(AdminTask::PRIORITIES[$this->task->priority] ?? $this->task->priority));

        if ($this->task->due_at) {
            $mail->line('Due: '.$this->task->due_at->format('D, d M Y'));
        }

        return $mail->action('Open the task', route('admin.tasks.show', $this->task));
    }
}
