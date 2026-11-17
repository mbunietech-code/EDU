<?php

namespace App\Notifications\User;

use App\Models\ResearchRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * News on a customer's research work request: a new stage, a message from
 * the team, a quote, drafts or the final work. Never carries internal notes.
 */
class ResearchRequestUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $event  stage | message | quote | files | cancelled | reopened | completed
     * @param  bool  $mail  also e-mail it (important steps only, not every chat line)
     */
    public function __construct(
        public ResearchRequest $request,
        public string $event,
        public string $message,
        public bool $mail = false,
    ) {
    }

    public function via($notifiable): array
    {
        return $this->mail ? ['database', 'mail'] : ['database'];
    }

    private function url(): string
    {
        return route('user.research-requests.show', $this->request->id);
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'research_request',
            'event' => $this->event,
            'research_request_id' => $this->request->id,
            'title' => $this->request->reference.' · '.$this->request->title,
            'message' => $this->message,
            'url' => $this->url(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->request->reference.': '.$this->request->title)
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($this->message)
            ->action('Open your request', $this->url());
    }
}
