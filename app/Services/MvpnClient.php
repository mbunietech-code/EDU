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
    /** Per-call override of services.mvpn.timeout (seconds). */
    private ?int $timeout = null;

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

    /**
     * VPN payment screenshots waiting for review, for the admin menu badge.
     * Cached a minute; null when the VPN can't be reached (no badge shown).
     */
    public function paymentsToReview(): ?int
    {
        if (! $this->configured()) {
            return null;
        }

        // -1 = VPN unreachable; cached too, so a VPN outage never slows the admin.
        $n = \Illuminate\Support\Facades\Cache::remember('mvpn:payments_to_review', 60, function () {
            $this->timeout = 3;
            try {
                return (int) ($this->admin('GET', 'invoices', ['status' => 'review'])['meta']['total'] ?? 0);
            } catch (RuntimeException) {
                return -1;
            } finally {
                $this->timeout = null;
            }
        });

        return $n >= 0 ? $n : null;
    }

    /**
     * VPN management endpoints (/api/partner/admin/...).
     *
     * @param  array<string,mixed>  $query
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function admin(string $method, string $path, array $query = [], array $payload = []): array
    {
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');
        $uri = '/api/partner/admin/'.ltrim($path, '/').($query ? '?'.http_build_query($query) : '');

        return $this->send(strtoupper($method), $uri, $payload);
    }

    /**
     * A file from the VPN (payment screenshot), streamed through EduHub so the
     * reviewer never needs a VPN login.
     *
     * @return array{0:string,1:string} [bytes, content type]
     */
    public function adminFile(string $path): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('MVPN_PARTNER_SECRET is not set');
        }
        $uri = '/api/partner/admin/'.ltrim($path, '/');
        $ts = time();

        try {
            $res = Http::withHeaders([
                'X-Mvpn-Timestamp' => (string) $ts,
                'X-Mvpn-Signature' => self::sign((string) config('services.mvpn.partner_secret'), $ts, 'GET', $uri, ''),
            ])->timeout(config('services.mvpn.timeout'))->get(config('services.mvpn.url').$uri);
        } catch (ConnectionException) {
            throw new RuntimeException('VPN server unreachable');
        }
        if (! $res->successful()) {
            throw new RuntimeException('VPN API error: HTTP '.$res->status(), $res->status());
        }

        return [$res->body(), $res->header('Content-Type') ?: 'application/octet-stream'];
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
            $request = Http::withHeaders($headers)->timeout($this->timeout ?? config('services.mvpn.timeout'));
            /** @var Response $res */
            $res = $method === 'GET'
                ? $request->get(config('services.mvpn.url').$uri)
                : $request->withBody($body, 'application/json')->send($method, config('services.mvpn.url').$uri);
        } catch (ConnectionException) {
            throw new RuntimeException('VPN server unreachable');
        }

        if (! $res->successful()) {
            $error = $res->json('message') ?? $res->json('error') ?? ('HTTP '.$res->status());
            throw new RuntimeException("VPN API error: {$error}", $res->status());
        }

        return (array) $res->json();
    }
}
