<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Signed client for the Mbunie VPN partner API (vpn.mbuniehub.com/api/partner).
 * Mirrors the VPN side's VerifyPartnerSignature:
 *   hex(hmac_sha256(secret, "{ts}\n{METHOD}\n{path?query}\n{sha256(body)}"))
 */
class MvpnClient
{
    public function configured(): bool
    {
        return strlen((string) config('services.mvpn.partner_secret')) >= 32;
    }

    /** @return array<string,mixed> */
    public function stats(): array
    {
        return $this->send('GET', '/api/partner/stats');
    }

    /** @return array<string,mixed> */
    public function customer(int $eduhubUserId): array
    {
        return $this->send('GET', "/api/partner/customers/{$eduhubUserId}");
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function activate(array $payload): array
    {
        return $this->send('POST', '/api/partner/activations', $payload);
    }

    /** @return array<string,mixed> plans as published by the VPN (public endpoint) */
    public function plans(): array
    {
        return $this->send('GET', '/api/plans', signed: false);
    }

    public static function sign(string $secret, int $ts, string $method, string $uri, string $body): string
    {
        return hash_hmac('sha256', implode("\n", [$ts, strtoupper($method), $uri, hash('sha256', $body)]), $secret);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     *
     * @throws RuntimeException with a safe message (never the secret)
     */
    private function send(string $method, string $uri, array $payload = [], bool $signed = true): array
    {
        if ($signed && ! $this->configured()) {
            throw new RuntimeException('MVPN_PARTNER_SECRET is not set');
        }

        $body = $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES) : '';
        $ts = time();
        $headers = ['Accept' => 'application/json'];
        if ($signed) {
            $headers['X-Mvpn-Timestamp'] = (string) $ts;
            $headers['X-Mvpn-Signature'] = self::sign((string) config('services.mvpn.partner_secret'), $ts, $method, $uri, $body);
        }

        try {
            $request = Http::withHeaders($headers)->timeout(config('services.mvpn.timeout'));
            /** @var Response $res */
            $res = $method === 'GET'
                ? $request->get(config('services.mvpn.url').$uri)
                : $request->withBody($body, 'application/json')->send($method, config('services.mvpn.url').$uri);
        } catch (ConnectionException) {
            throw new RuntimeException('VPN server unreachable');
        }

        if (! $res->successful()) {
            $error = $res->json('error') ?? ('HTTP '.$res->status());
            throw new RuntimeException("VPN API error: {$error}", $res->status());
        }

        return (array) $res->json();
    }
}
