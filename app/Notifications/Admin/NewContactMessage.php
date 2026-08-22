<?php

namespace App\Notifications\Admin;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class NewContactMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactMessage $contactMessage)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'contact_message_id' => $this->contactMessage->id,
            'name' => $this->contactMessage->name,
            'email' => $this->contactMessage->email,
            'subject' => $this->contactMessage->subject,
            'message' => 'New contact message: ' . $this->contactMessage->subject,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New contact message: ' . $this->contactMessage->subject)
            ->line('From: ' . $this->contactMessage->name . ' (' . $this->contactMessage->email . ')')
            ->line('Subject: ' . $this->contactMessage->subject)
            ->line($this->contactMessage->message)
            ->action('View in Admin', url('/admin/contact-messages/' . $this->contactMessage->id));
    }
}
