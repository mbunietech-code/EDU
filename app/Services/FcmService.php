<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Minimal Firebase Cloud Messaging (HTTP v1) sender. No SDK — signs a JWT with
 * the service-account key, exchanges it for an access token (cached), and posts
 * one message per device token.
 *
 * Configure via config/services.php -> fcm.
 */
class FcmService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return (bool) config('services.fcm.enabled') && $this->credentialsPath() !== null;
    }

    /**
     * The configured path, or the first *.json in storage/app/firebase/
     * (Firebase downloads the service-account key with a long default name).
     */
    private function credentialsPath(): ?string
    {
        $configured = config('services.fcm.credentials');
        if (is_string($configured) && is_file($configured)) {
            return $configured;
        }

        foreach (glob(storage_path('app/firebase/*.json')) ?: [] as $file) {
            return $file;
        }

        return null;
    }

    /**
     * Send a push to every device token belonging to $userIds.
     *
     * @param  iterable<int>  $userIds
     * @param  array<string,string>  $data  extra key/value payload (strings only)
     */
    public function sendToUsers(iterable $userIds, string $title, string $body, array $data = []): void
    {
        $ids = collect($userIds)->filter()->unique()->values();
        if ($ids->isEmpty() || ! $this->isConfigured()) {
            return;
        }

        $tokens = DeviceToken::whereIn('user_id', $ids)->pluck('token', 'id');
        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $accessToken = $this->accessToken();
            $projectId = $this->projectId();
        } catch (Throwable $e) {
            Log::warning('FCM: could not authenticate', ['error' => $e->getMessage()]);

            return;
        }

        $stale = [];

        foreach ($tokens as $id => $token) {
            try {
                $res = Http::withToken($accessToken)
                    ->acceptJson()
                    ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                        'message' => [
                            'token' => $token,
                            'notification' => ['title' => $title, 'body' => $body],
                            'data' => array_map('strval', $data),
                            'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'mhub_default']],
                            'apns' => ['headers' => ['apns-priority' => '10']],
                        ],
                    ]);

                if ($res->status() === 404 || $res->status() === 400) {
                    // UNREGISTERED / invalid token — drop it.
                    $stale[] = $id;
                }
            } catch (Throwable $e) {
                Log::warning('FCM: send failed', ['error' => $e->getMessage()]);
            }
        }

        if ($stale) {
            DeviceToken::whereIn('id', $stale)->delete();
        }
    }

    private function credentials(): array
    {
        $path = $this->credentialsPath();
        $json = json_decode((string) file_get_contents((string) $path), true);

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new \RuntimeException('Invalid FCM service-account file.');
        }

        return $json;
    }

    private function projectId(): string
    {
        return config('services.fcm.project_id') ?: ($this->credentials()['project_id'] ?? '');
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm.access_token', now()->addMinutes(50), function () {
            $sa = $this->credentials();
            $now = time();

            $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->b64(json_encode([
                'iss' => $sa['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signingInput = $header.'.'.$claims;
            openssl_sign($signingInput, $signature, $sa['private_key'], 'SHA256');
            $jwt = $signingInput.'.'.$this->b64($signature);

            $res = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            $token = $res->json('access_token');
            if (! $token) {
                throw new \RuntimeException('FCM token exchange failed: '.$res->body());
            }

            return $token;
        });
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
