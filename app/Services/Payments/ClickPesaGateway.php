<?php

namespace App\Services\Payments;

use App\Models\GatewayPayment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ClickPesa USSD push. The network is detected from the phone number, and
 * every callback is confirmed against ClickPesa's payment status endpoint.
 */
class ClickPesaGateway implements MobileMoneyGateway
{
    private const SUCCESS = ['SUCCESS', 'SETTLED'];

    private const FAILED = ['FAILED', 'REJECTED', 'CANCELLED', 'EXPIRED', 'REVERSED'];

    public function key(): string
    {
        return 'clickpesa';
    }

    public function label(): string
    {
        return $this->config('label', 'ClickPesa');
    }

    public function isEnabled(): bool
    {
        return $this->config('enabled') && filled($this->config('client_id')) && filled($this->config('api_key'));
    }

    public function usesRedirect(): bool
    {
        return false;
    }

    public function networks(): array
    {
        return [];
    }

    public function initiate(GatewayPayment $payment): ?string
    {
        try {
            $response = $this->client()->post($this->url('/payments/initiate-ussd-push-request'), $this->withChecksum([
                'amount' => (string) (int) round((float) $payment->amount),
                'currency' => $payment->currency,
                'orderReference' => $payment->external_id,
                'phoneNumber' => $payment->phone,
            ]));
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach ClickPesa. Please try again.', previous: $e);
        }

        if (! $response->successful()) {
            Log::warning('ClickPesa USSD push failed', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

            throw GatewayException::fromResponse($response, 'ClickPesa could not start the payment.');
        }

        return $response->json('id');
    }

    public function fetchStatus(GatewayPayment $payment): ?array
    {
        try {
            $response = $this->client()->get($this->url('/payments/'.rawurlencode($payment->external_id)));
        } catch (ConnectionException) {
            return ['status' => 'pending', 'reference' => null, 'amount' => null];
        }

        if ($response->status() === 404) {
            return ['status' => 'pending', 'reference' => null, 'amount' => null];
        }

        if (! $response->successful()) {
            return ['status' => 'pending', 'reference' => null, 'amount' => null];
        }

        $data = $response->json();
        $record = array_is_list($data ?? []) ? ($data[0] ?? []) : ($data ?? []);
        $status = strtoupper((string) ($record['status'] ?? ''));

        return [
            'status' => in_array($status, self::SUCCESS, true) ? 'success' : (in_array($status, self::FAILED, true) ? 'failed' : 'pending'),
            'reference' => $record['paymentReference'] ?? $record['id'] ?? null,
            // collectedAmount is what reaches us after ClickPesa's fee, so it is
            // always below the order total. The push amount is fixed by us and
            // the customer cannot change it: SUCCESS means the full amount.
            'amount' => null,
            'message' => filled($record['message'] ?? null) && strtolower((string) $record['message']) !== 'success' ? (string) $record['message'] : null,
        ];
    }

    /**
     * When "checksum" is switched on in the ClickPesa dashboard every request
     * must carry one: HMAC-SHA256 (hex) of the payload with keys sorted at
     * every level, as compact JSON, using the checksum key.
     */
    public function withChecksum(array $payload): array
    {
        $key = (string) $this->config('checksum_key');

        if ($key === '') {
            return $payload;
        }

        $payload['checksum'] = hash_hmac('sha256', json_encode(self::canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $key);

        return $payload;
    }

    protected static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map([self::class, 'canonicalize'], $value);
        }

        ksort($value, SORT_STRING);

        return array_map([self::class, 'canonicalize'], $value);
    }

    public function parseCallback(Request $request): array
    {
        return [
            'external_id' => $request->input('data.orderReference', $request->input('orderReference')),
            'status' => null,
            'reference' => null,
            'amount' => null,
            'verify' => true,
        ];
    }

    protected function client()
    {
        return Http::acceptJson()->timeout(30)->withHeaders(['Authorization' => $this->token()]);
    }

    public function testConnection(): void
    {
        $this->forgetToken();
        $this->token();
    }

    public function forgetToken(): void
    {
        Cache::forget('clickpesa_token');
    }

    protected function token(): string
    {
        return Cache::remember('clickpesa_token', now()->addMinutes(50), function () {
            try {
                $response = Http::acceptJson()->timeout(30)->withHeaders([
                    'client-id' => $this->config('client_id'),
                    'api-key' => $this->config('api_key'),
                ])->post($this->url('/generate-token'));
            } catch (ConnectionException $e) {
                throw new GatewayException('Could not reach ClickPesa. Please try again.', previous: $e);
            }

            $token = (string) $response->json('token');

            if (! $response->successful() || $token === '') {
                Log::error('ClickPesa token request failed', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

                throw GatewayException::fromResponse($response, 'Mobile money payments are temporarily unavailable.');
            }

            return str_starts_with($token, 'Bearer ') ? $token : 'Bearer '.$token;
        });
    }

    protected function url(string $path): string
    {
        return rtrim($this->config('base_url'), '/').$path;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("payments.gateways.clickpesa.{$key}", $default);
    }
}
