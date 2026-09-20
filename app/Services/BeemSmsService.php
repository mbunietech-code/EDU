<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends SMS through Beem Africa. Credentials live in the settings table
 * (group "alerts"), the secret encrypted, so they can be managed from the
 * admin UI without shell access — same approach as the mail settings.
 */
class BeemSmsService
{
    private const API_URL = 'https://apisms.beem.africa/v1/send';

    public function __construct(private CredentialService $credentials)
    {
    }

    public function isConfigured(): bool
    {
        return filled(Setting::get('beem_api_key'))
            && filled(Setting::get('beem_secret_key'))
            && filled(Setting::get('beem_sender_id'));
    }

    /**
     * @param  list<string>  $numbers
     */
    public function send(array $numbers, string $message): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Beem SMS is not configured.');
        }

        $recipients = [];
        foreach (array_values(array_filter(array_map([$this, 'normalize'], $numbers))) as $i => $number) {
            $recipients[] = ['recipient_id' => (string) ($i + 1), 'dest_addr' => $number];
        }

        if ($recipients === []) {
            throw new RuntimeException('No valid phone numbers to send to.');
        }

        $secret = $this->credentials->decryptValue(Setting::get('beem_secret_key'));

        $response = Http::withBasicAuth((string) Setting::get('beem_api_key'), (string) $secret)
            ->timeout(20)
            ->post(self::API_URL, [
                'source_addr' => Setting::get('beem_sender_id'),
                'schedule_time' => '',
                'encoding' => '0',
                'message' => $message,
                'recipients' => $recipients,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Beem API error '.$response->status().': '.$response->body());
        }
    }

    /**
     * Beem wants international format without "+": 0712345678 -> 255712345678.
     */
    public function normalize(string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '255'.substr($digits, 1);
        }

        return strlen($digits) >= 11 ? $digits : null;
    }
}
