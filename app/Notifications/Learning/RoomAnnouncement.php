<?php

namespace App\Notifications\Learning;

use App\Models\LearningRoom;
use App\Models\LearningRoomMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class RoomAnnouncement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LearningRoom $room, public LearningRoomMessage $announcement)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'learning.room_announcement',
            'room_id' => $this->room->id,
            'message_id' => $this->announcement->id,
            'title' => 'Announcement: '.$this->room->title,
            'message' => Str::limit($this->announcement->body, 200),
            'url' => $this->room->isLive()
                ? route('learn.rooms.live', $this->room)
                : route('learn.rooms.show', $this->room),
        ];
    }
}
