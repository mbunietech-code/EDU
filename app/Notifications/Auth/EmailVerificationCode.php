<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCode extends Notification
{
    use Queueable;

    public function __construct(
        public string $code,
        public int $expiresInMinutes = 15,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your MbunieEduHub verification code')
            ->greeting('Verify your email')
            ->line('Use this code to verify your MbunieEduHub account:')
            ->line($this->code)
            ->line("This code expires in {$this->expiresInMinutes} minutes.")
            ->action('Enter code', route('verification.notice'))
            ->line('If you did not create this account, you can ignore this email.');
    }
}
