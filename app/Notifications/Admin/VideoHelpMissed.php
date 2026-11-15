<?php

namespace App\Notifications\Admin;

use App\Models\VideoHelpRequest;
use Illuminate\Notifications\Notification;

/** Nobody answered a video help call in time: follow up with the member. */
class VideoHelpMissed extends Notification
{
    public function __construct(public VideoHelpRequest $call)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $name = $this->call->user?->name ?? 'A member';

        return [
            'type' => 'video_help_missed',
            'title' => 'Missed video help call',
            'video_help_request_id' => $this->call->id,
            'message' => 'Missed video help call from '.$name.'. Please follow up in Messages.',
            'url' => route('admin.video-help.index'),
        ];
    }
}
