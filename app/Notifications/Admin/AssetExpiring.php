<?php

namespace App\Notifications\Admin;

use App\Models\CompanyAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A company asset (domain, hosting, software…) ends soon or has ended. */
class AssetExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CompanyAsset $asset, public int $daysLeft)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    private function when(): string
    {
        return match (true) {
            $this->daysLeft < 0 => 'ended '.abs($this->daysLeft).' day(s) ago',
            $this->daysLeft === 0 => 'ends today',
            $this->daysLeft === 1 => 'ends tomorrow',
            default => 'ends in '.$this->daysLeft.' days',
        };
    }

    public function toDatabase($notifiable): array
    {
        return [
            'company_asset_id' => $this->asset->id,
            'message' => $this->asset->name.' '.$this->when().' ('.$this->asset->expires_at?->format('d M Y').')',
            'url' => route('admin.assets.index', ['status' => 'attention']),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->asset->name.' '.$this->when())
            ->line($this->asset->name.' ('.$this->asset->categoryLabel().') '.$this->when().'.')
            ->line('End date: '.$this->asset->expires_at?->format('D, d M Y'));

        if ($this->asset->vendor) {
            $mail->line('Bought from: '.$this->asset->vendor);
        }

        if ($this->asset->purchase_url) {
            $mail->action('Renew it', $this->asset->purchase_url);
        }

        return $mail->line('Mark it renewed in Admin → Company assets once paid.');
    }
}
