<?php

namespace App\Notifications\Admin;

use App\Models\Research;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResearchSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Research $research)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'research_id' => $this->research->id,
            'title' => $this->research->title,
            'author' => $this->research->author->name ?? 'A contributor',
            'message' => 'New research submitted for review: '.$this->research->title,
            'url' => url('/admin/research/'.$this->research->id),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Research submitted for review: '.$this->research->title)
            ->line(($this->research->author->name ?? 'A contributor').' submitted "'.$this->research->title.'" for review.')
            ->action('Review it', url('/admin/research/'.$this->research->id));
    }
}
