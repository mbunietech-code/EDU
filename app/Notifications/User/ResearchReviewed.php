<?php

namespace App\Notifications\User;

use App\Models\Research;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResearchReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $action  approved | published | rejected | changes_requested
     */
    public function __construct(public Research $research, public string $action, public ?string $comment = null)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    private function headline(): string
    {
        return match ($this->action) {
            'approved' => 'Your research "'.$this->research->title.'" was approved.',
            'published' => 'Your research "'.$this->research->title.'" is now published.',
            'rejected' => 'Your research "'.$this->research->title.'" was rejected.',
            'changes_requested' => 'Changes were requested on "'.$this->research->title.'".',
            default => 'Your research "'.$this->research->title.'" was reviewed.',
        };
    }

    public function toDatabase($notifiable): array
    {
        return [
            'research_id' => $this->research->id,
            'title' => $this->research->title,
            'action' => $this->action,
            'comment' => $this->comment,
            'message' => $this->headline(),
            'url' => url('/my-research/'.$this->research->id),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->headline())
            ->line($this->headline());

        if ($this->comment) {
            $mail->line('Reviewer note: '.$this->comment);
        }

        return $mail->action('Open in My Research', url('/my-research/'.$this->research->id));
    }
}
