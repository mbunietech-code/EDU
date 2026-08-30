<?php

namespace App\Notifications\Admin;

use App\Models\ErrorLog;
use Illuminate\Notifications\Notification;

class ErrorOccurred extends Notification
{
    public function __construct(public ErrorLog $errorLog)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'error',
            'error_log_id' => $this->errorLog->id,
            'exception' => class_basename($this->errorLog->exception),
            'url' => $this->errorLog->url,
            'message' => 'Server error: '.class_basename($this->errorLog->exception)
                .' — '.\Illuminate\Support\Str::limit($this->errorLog->message, 80),
        ];
    }
}
