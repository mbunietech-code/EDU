<?php

namespace App\Notifications\Learning;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstructorAccessGranted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?User $grantedBy = null)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    private function headline(): string
    {
        return 'You are now an instructor — you can host live classes and upload lessons.';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'learning.instructor_granted',
            'title' => 'Instructor access granted',
            'message' => $this->headline(),
            'granted_by' => $this->grantedBy?->name,
            'url' => route('studio.home'),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Instructor access granted')
            ->line($this->headline())
            ->line('Open the Teaching Studio to schedule your first live class or upload a lesson.')
            ->action('Open the Teaching Studio', route('studio.home'));
    }
}
