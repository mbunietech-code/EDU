<?php

namespace App\Notifications\Learning;

use App\Models\LearningRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LiveSessionStarted extends Notification implements ShouldQueue
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
        return [
            'type' => 'learning.room_started',
            'room_id' => $this->room->id,
            'title' => 'Live now',
            'message' => '"'.$this->room->title.'" is live — join the class now.',
            'url' => route('learn.rooms.live', $this->room),
        ];
    }
}
