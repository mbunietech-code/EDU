<?php

namespace App\Notifications\Admin;

use Illuminate\Notifications\Notification;

/**
 * The AI Assistant "calls" an admin about a problem (see AiWatchdog): rings
 * in the popup on every admin page until opened or dismissed, and
 * PushDatabaseNotification sends it to the MHub app as a phone push.
 * Sent at once, not queued: a waiting customer should not wait for cron.
 */
class AiAlert extends Notification
{
    /** @param array<string,mixed> $problem */
    public function __construct(public array $problem)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'ai_alert',
            'kind' => $this->problem['kind'],
            'severity' => $this->problem['severity'],
            'fingerprint' => $this->problem['fingerprint'],
            'title' => 'AI Assistant: '.$this->problem['title'],
            'message' => $this->problem['message'],
            'url' => $this->problem['url'],
            'ask' => $this->problem['ask'],
        ];
    }
}
