<?php

namespace App\Notifications\Admin;

use App\Models\ResearchRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A customer asked for research work to be done. Staff with research_requests.view only. */
class NewResearchRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ResearchRequest $request)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    private function headline(): string
    {
        return 'New research request '.$this->request->reference.': '.$this->request->title;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'research_request_admin',
            'research_request_id' => $this->request->id,
            'title' => 'New research work request',
            'message' => $this->headline().' ('.($this->request->user->name ?? 'a customer').')',
            'url' => route('admin.research-requests.show', $this->request->id),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $r = $this->request;

        return (new MailMessage)
            ->subject($this->headline())
            ->line(($r->user->name ?? 'A customer').' asked for: '.$r->typeLabel().' ('.$r->levelLabel().').')
            ->line($r->deadline ? 'Deadline: '.$r->deadline->format('d M Y') : 'No deadline given.')
            ->action('Open the request', route('admin.research-requests.show', $r->id));
    }
}
