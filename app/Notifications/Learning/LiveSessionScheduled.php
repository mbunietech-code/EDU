<?php

namespace App\Notifications\Learning;

use App\Models\LearningRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LiveSessionScheduled extends Notification implements ShouldQueue
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
        $when = $this->room->scheduled_at?->format('D, d M Y · H:i');

        return [
            'type' => 'learning.room_scheduled',
            'room_id' => $this->room->id,
            'title' => 'Live class scheduled',
            'message' => $when
                ? '"'.$this->room->title.'" is scheduled for '.$when.'.'
                : '"'.$this->room->title.'" has been scheduled.',
            'url' => route('learn.rooms.show', $this->room),
        ];
    }
}
