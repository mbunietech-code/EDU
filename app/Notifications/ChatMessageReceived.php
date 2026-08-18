<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ChatMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Conversation $conversation, public string $fromName)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'message' => $this->fromName . ' sent you a new chat message.',
        ];
    }
}