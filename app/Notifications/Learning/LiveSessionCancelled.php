<?php

namespace App\Notifications\Learning;

use App\Models\LearningRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LiveSessionCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LearningRoom $room)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $message = '"'.$this->room->title.'" has been cancelled.';

        if ($this->room->cancel_reason) {
            $message .= ' Reason: '.$this->room->cancel_reason;
        }

        return [
            'type' => 'learning.room_cancelled',
            'room_id' => $this->room->id,
            'title' => 'Live class cancelled',
            'message' => $message,
            'reason' => $this->room->cancel_reason,
            'url' => route('learn.rooms.show', $this->room),
        ];
    }
}
