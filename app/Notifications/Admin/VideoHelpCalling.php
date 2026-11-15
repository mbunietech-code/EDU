<?php

namespace App\Notifications\Admin;

use App\Models\VideoHelpRequest;
use Illuminate\Notifications\Notification;

/**
 * "A member is calling for video help." Sent at once (not queued: the call
 * only rings for a couple of minutes); PushDatabaseNotification turns it
 * into a phone push for the MHub app.
 */
class VideoHelpCalling extends Notification
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
            'type' => 'video_help_call',
            'title' => 'Video help call',
            'video_help_request_id' => $this->call->id,
            'message' => $name.' is calling for video help. Open the admin panel to answer.',
            'url' => route('admin.video-help.index'),
        ];
    }
}
