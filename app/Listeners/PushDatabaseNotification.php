<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\FcmService;
use App\Services\VideoHelpService;
use Illuminate\Notifications\Events\NotificationSent;

use function Illuminate\Support\defer;

/**
 * Every notification that lands in the `notifications` table is also pushed to
 * the user's MHub app devices via FCM. The send is deferred until after the
 * response so it never slows a request (e.g. placing an order).
 */
class PushDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        $notifiable = $event->notifiable;
        if (! $notifiable instanceof User) {
            return;
        }

        $data = $this->payload($event->notification, $notifiable);
        $body = (string) ($data['message'] ?? 'You have a new notification');
        $title = (string) ($data['title'] ?? config('app.name', 'MbunieEduHub'));

        $extra = ['type' => (string) ($data['type'] ?? class_basename($event->notification))];
        foreach (['order_id', 'payment_id', 'subscription_id', 'error_log_id', 'conversation_id', 'room_id', 'video_id', 'course_id', 'research_request_id', 'url'] as $k) {
            if (isset($data[$k])) {
                $extra[$k] = (string) $data[$k];
            }
        }

        // A video help call rings on the app's "calls" channel and is dropped once the call stops ringing.
        $options = $extra['type'] === 'video_help_call'
            ? ['channel' => 'mhub_calls', 'ttl' => app(VideoHelpService::class)->ringSeconds()]
            : [];

        $userId = $notifiable->id;

        defer(function () use ($userId, $title, $body, $extra, $options) {
            app(FcmService::class)->sendToUsers([$userId], $title, $body, $extra, $options);
        });
    }

    private function payload($notification, User $notifiable): array
    {
        if (method_exists($notification, 'toDatabase')) {
            return (array) $notification->toDatabase($notifiable);
        }
        if (method_exists($notification, 'toArray')) {
            return (array) $notification->toArray($notifiable);
        }

        return [];
    }
}
