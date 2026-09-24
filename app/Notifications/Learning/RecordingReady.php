<?php

namespace App\Notifications\Learning;

use App\Models\LearningRoomRecording;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Sent to the room's host — the link opens the room in the Teaching Studio. */
class RecordingReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LearningRoomRecording $recording)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $room = $this->recording->room;

        return [
            'type' => 'learning.recording_ready',
            'room_id' => $this->recording->learning_room_id,
            'recording_id' => $this->recording->id,
            'title' => 'Recording ready',
            'message' => 'The recording of "'.($room->title ?? 'your live class').'" is ready to review and share.',
            'url' => route('studio.rooms.show', $this->recording->learning_room_id),
        ];
    }
}
