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

    public function networks(): array
    {
        return [];
    }

    public function initiate(GatewayPayment $payment): ?string
    {
        try {
            $response = $this->client()->post($this->url('/payments/initiate-ussd-push-request'), [
                'amount' => (string) (int) round((float) $payment->amount),
                'currency' => $payment->currency,
                'orderReference' => $payment->external_id,
                'phoneNumber' => $payment->phone,
            ]);
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach ClickPesa. Please try again.', previous: $e);
        }

        if (! $response->successful()) {
            Log::warning('ClickPesa USSD push failed', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

            throw new GatewayException($response->json('message') ?: 'ClickPesa could not start the payment. Check the phone number.');
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
            'amount' => isset($record['collectedAmount']) ? (float) $record['collectedAmount'] : null,
        ];
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

                throw new GatewayException('Mobile money payments are temporarily unavailable.');
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
