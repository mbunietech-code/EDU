<?php

namespace App\Notifications\Admin;

use Illuminate\Notifications\Notification;

/** A new problem found by the security scan (php artisan security:scan). */
class SecurityAlert extends Notification
{
    public function __construct(public string $title, public string $detail)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'error', // shown in red like server errors
            'message' => 'Security: '.$this->title.' — '.\Illuminate\Support\Str::limit($this->detail, 120),
        ];
    }
}
