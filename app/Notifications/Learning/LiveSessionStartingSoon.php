<?php

namespace App\Notifications\Learning;

use App\Models\LearningRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LiveSessionStartingSoon extends Notification implements ShouldQueue
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
        $at = $this->room->scheduled_at?->format('H:i');

        return [
            'type' => 'learning.room_starting_soon',
            'room_id' => $this->room->id,
            'title' => 'Live class starting soon',
            'message' => '"'.$this->room->title.'" starts '.($at ? 'at '.$at : 'soon').'.',
            'url' => route('learn.rooms.show', $this->room),
        ];
    }
}
