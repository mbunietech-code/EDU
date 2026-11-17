<?php

namespace App\Notifications\Admin;

use App\Models\ResearchRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The customer did something on a research work request (replied, accepted
 * or declined the quote, asked for a revision, cancelled, accepted the work),
 * or a deadline is near. Staff with research_requests.view only.
 */
class ResearchRequestActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ResearchRequest $request, public string $event, public string $message)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'research_request_admin',
            'event' => $this->event,
            'research_request_id' => $this->request->id,
            'title' => $this->request->reference.' · '.$this->request->title,
            'message' => $this->message,
            'url' => route('admin.research-requests.show', $this->request->id),
        ];
    }
}
