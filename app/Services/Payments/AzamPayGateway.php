<?php

namespace App\Services\Payments;

use App\Models\GatewayPayment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AzamPay mobile network (MNO) checkout: M-Pesa, Mixx by Yas, Airtel,
 * Halopesa and AzamPesa. AzamPay has no status lookup, so the result comes
 * from the callback registered on the AzamPay merchant dashboard.
 */
class AzamPayGateway implements MobileMoneyGateway
{
    public function key(): string
    {
        return 'azampay';
    }

    public function label(): string
    {
        return $this->config('label', 'AzamPay');
    }

    public function isEnabled(): bool
    {
        return $this->config('enabled')
            && filled($this->config('app_name'))
            && filled($this->config('client_id'))
            && filled($this->config('client_secret'));
    }

    public function networks(): array
    {
        return $this->config('networks', []);
    }

    public function initiate(GatewayPayment $payment): ?string
    {
        try {
            $response = Http::acceptJson()
                ->timeout(30)
                ->withToken($this->token())
                ->withHeaders(array_filter(['X-API-Key' => $this->config('api_key')]))
                ->post($this->url('checkout', '/azampay/mno/checkout'), [
                    'accountNumber' => $payment->phone,
                    'amount' => (string) (int) round((float) $payment->amount),
                    'currency' => $payment->currency,
                    'externalId' => $payment->external_id,
                    'provider' => $payment->network,
                    'additionalProperties' => (object) [],
                ]);
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach AzamPay. Please try again.', previous: $e);
        }

        if (! $response->successful() || $response->json('success') === false) {
            Log::warning('AzamPay checkout failed', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

            throw new GatewayException($response->json('message') ?: 'AzamPay could not start the payment. Check the number and network.');
        }

        return $response->json('transactionId');
    }

    public function fetchStatus(GatewayPayment $payment): ?array
    {
        return null;
    }

    public function parseCallback(Request $request): array
    {
        $status = strtolower((string) $request->input('transactionstatus', $request->input('transactionStatus', '')));

        return [
            'external_id' => $request->input('utilityref', $request->input('externalreference', $request->input('externalId'))),
            'status' => $status === 'success' ? 'success' : ($status !== '' ? 'failed' : null),
            'reference' => $request->input('reference', $request->input('fspReferenceId')),
            'amount' => $request->filled('amount') ? (float) $request->input('amount') : null,
            'verify' => false,
        ];
    }

    protected function token(): string
    {
        return Cache::remember('azampay_token_'.$this->config('environment'), now()->addMinutes(50), function () {
            try {
                $response = Http::acceptJson()->timeout(30)->post($this->url('auth', '/AppRegistration/GenerateToken'), [
                    'appName' => $this->config('app_name'),
                    'clientId' => $this->config('client_id'),
                    'clientSecret' => $this->config('client_secret'),
                ]);
            } catch (ConnectionException $e) {
                throw new GatewayException('Could not reach AzamPay. Please try again.', previous: $e);
            }

            $token = $response->json('data.accessToken');

            if (! $response->successful() || blank($token)) {
                Log::error('AzamPay token request failed', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

                throw new GatewayException('Mobile money payments are temporarily unavailable.');
            }

            return $token;
        });
    }

    protected function url(string $service, string $path): string
    {
        $environment = $this->config('environment') === 'live' ? 'live' : 'sandbox';

        return rtrim($this->config("urls.{$environment}.{$service}"), '/').$path;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("payments.gateways.azampay.{$key}", $default);
    }
}
