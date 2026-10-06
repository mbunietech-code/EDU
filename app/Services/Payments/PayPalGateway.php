<?php

namespace App\Services\Payments;

use App\Models\GatewayPayment;
use App\Services\CurrencyRateService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayPal Checkout (Orders v2). The customer approves on PayPal's page (PayPal
 * balance or card); we capture on return, or the cron job captures orders
 * approved by customers who never came back. Charged in USD: PayPal has no TZS.
 */
class PayPalGateway implements MobileMoneyGateway
{
    public function __construct(protected CurrencyRateService $rates)
    {
    }

    public function key(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return $this->config('label', 'PayPal');
    }

    public function usesRedirect(): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->config('enabled') && filled($this->config('client_id')) && filled($this->config('client_secret'));
    }

    public function networks(): array
    {
        return [];
    }

    /**
     * Amount in the PayPal currency for a TZS amount.
     */
    public function chargeFor(float $tzs): ?float
    {
        $converted = $this->rates->convert($tzs, $this->currency());

        return $converted ? max(0.01, round($converted, 2)) : null;
    }

    public function currency(): string
    {
        return strtoupper($this->config('currency', 'USD'));
    }

    public function initiate(GatewayPayment $payment): ?string
    {
        $charge = $this->chargeFor((float) $payment->amount);

        if (! $charge) {
            throw new GatewayException('PayPal is temporarily unavailable. Please use another payment method.');
        }

        $orderNumber = $payment->order?->order_number ?? $payment->order_id;

        try {
            $response = $this->client()
                ->withHeaders(['PayPal-Request-Id' => $payment->external_id])
                ->post($this->url('/v2/checkout/orders'), [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => (string) $payment->order_id,
                        'custom_id' => $payment->external_id,
                        'invoice_id' => $payment->external_id,
                        'description' => 'Order '.$orderNumber,
                        'amount' => [
                            'currency_code' => $this->currency(),
                            'value' => number_format($charge, 2, '.', ''),
                        ],
                    ]],
                    'application_context' => [
                        'brand_name' => config('app.name'),
                        'user_action' => 'PAY_NOW',
                        'shipping_preference' => 'NO_SHIPPING',
                        'return_url' => route('user.payments.paypal.return', $payment->order_id),
                        'cancel_url' => route('user.payments.paypal.cancel', $payment->order_id),
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach PayPal. Please try again.', previous: $e);
        }

        $approveUrl = collect($response->json('links', []))
            ->first(fn ($link) => in_array($link['rel'] ?? '', ['approve', 'payer-action'], true))['href'] ?? null;

        if (! $response->successful() || ! $approveUrl) {
            Log::warning('PayPal order creation failed', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

            throw GatewayException::fromResponse($response, 'PayPal could not start the payment.');
        }

        $payment->update([
            'charged_amount' => $charge,
            'charged_currency' => $this->currency(),
            'redirect_url' => $approveUrl,
        ]);

        return $response->json('id');
    }

    public function fetchStatus(GatewayPayment $payment): ?array
    {
        if (blank($payment->provider_transaction_id)) {
            return null;
        }

        try {
            $order = $this->client()->get($this->url('/v2/checkout/orders/'.$payment->provider_transaction_id));

            if ($order->successful() && $order->json('status') === 'APPROVED') {
                $order = $this->client()
                    ->withHeaders(['PayPal-Request-Id' => $payment->external_id.'-capture'])
                    ->withBody('{}', 'application/json')
                    ->post($this->url('/v2/checkout/orders/'.$payment->provider_transaction_id.'/capture'));

                if ($order->status() === 422) {
                    // Already captured by a parallel request: read it back.
                    $order = $this->client()->get($this->url('/v2/checkout/orders/'.$payment->provider_transaction_id));
                }
            }
        } catch (ConnectionException) {
            return ['status' => 'pending', 'reference' => null, 'amount' => null];
        }

        if (! $order->successful()) {
            Log::warning('PayPal order lookup failed', ['status' => $order->status(), 'body' => $order->json() ?? $order->body()]);

            return ['status' => 'pending', 'reference' => null, 'amount' => null];
        }

        if ($email = $order->json('payer.email_address')) {
            $payment->update(['payer_email' => $email]);
        }

        $orderStatus = $order->json('status');

        if ($orderStatus === 'VOIDED') {
            return ['status' => 'failed', 'reference' => null, 'amount' => null];
        }

        if ($orderStatus !== 'COMPLETED') {
            return ['status' => 'pending', 'reference' => null, 'amount' => null];
        }

        $capture = $order->json('purchase_units.0.payments.captures.0', []);
        $captureStatus = $capture['status'] ?? null;

        if (in_array($captureStatus, ['DECLINED', 'FAILED'], true)) {
            return ['status' => 'failed', 'reference' => $capture['id'] ?? null, 'amount' => null];
        }

        if ($captureStatus !== 'COMPLETED') {
            return ['status' => 'pending', 'reference' => $capture['id'] ?? null, 'amount' => null];
        }

        // Report the amount in TZS so it can be compared with the order total.
        $captured = (float) ($capture['amount']['value'] ?? 0);
        $charged = (float) $payment->charged_amount;
        $tzs = $charged > 0 && $captured + 0.005 < $charged
            ? round((float) $payment->amount * $captured / $charged, 2)
            : (float) $payment->amount;

        return ['status' => 'success', 'reference' => $capture['id'] ?? null, 'amount' => $tzs];
    }

    public function parseCallback(Request $request): array
    {
        // Results come from the return URL and the cron job, not from webhooks.
        return ['external_id' => null, 'status' => null, 'reference' => null, 'amount' => null, 'verify' => true];
    }

    protected function client()
    {
        return Http::acceptJson()->timeout(30)->withToken($this->token());
    }

    public function testConnection(): void
    {
        $this->forgetToken();
        $this->token();
    }

    public function forgetToken(): void
    {
        Cache::forget('paypal_token_'.$this->mode());
    }

    protected function token(): string
    {
        return Cache::remember('paypal_token_'.$this->mode(), now()->addMinutes(50), function () {
            try {
                $response = Http::acceptJson()->asForm()->timeout(30)
                    ->withBasicAuth($this->config('client_id'), $this->config('client_secret'))
                    ->post($this->url('/v1/oauth2/token'), ['grant_type' => 'client_credentials']);
            } catch (ConnectionException $e) {
                throw new GatewayException('Could not reach PayPal. Please try again.', previous: $e);
            }

            $token = $response->json('access_token');

            if (! $response->successful() || blank($token)) {
                Log::error('PayPal token request failed', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

                throw GatewayException::fromResponse($response, 'PayPal is temporarily unavailable.');
            }

            return $token;
        });
    }

    protected function mode(): string
    {
        return $this->config('mode') === 'live' ? 'live' : 'sandbox';
    }

    protected function url(string $path): string
    {
        return rtrim($this->config('urls.'.$this->mode()), '/').$path;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("payments.gateways.paypal.{$key}", $default);
    }
}
