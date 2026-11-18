<?php

namespace App\Services\Payments;

use App\Models\GatewayPayment;
use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PesaPal API 3.0. The customer pays on PesaPal's secure form, shown inside
 * our own checkout page (never a jump to another site), with a Tanzanian
 * mobile money number (M-Pesa, Airtel Money, Mixx by Yas), a card or a bank,
 * in TZS. Results arrive by IPN and are always re-checked with
 * GetTransactionStatus before an order is marked paid.
 */
class PesaPalGateway implements AcknowledgesCallback, MobileMoneyGateway
{
    /** PesaPal status_code values. */
    private const COMPLETED = 1;

    private const FAILED = 2;

    private const REVERSED = 3;

    public function key(): string
    {
        return 'pesapal';
    }

    public function label(): string
    {
        return $this->config('label', 'PesaPal');
    }

    public function usesRedirect(): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->config('enabled') && filled($this->config('consumer_key')) && filled($this->config('consumer_secret'));
    }

    public function networks(): array
    {
        return [];
    }

    public function initiate(GatewayPayment $payment): ?string
    {
        $user = $payment->user;
        $name = trim((string) ($payment->checkoutDetails['name'] ?? $user?->name));
        [$first, $last] = array_pad(preg_split('/\s+/', $name, 2) ?: [], 2, '');
        $fromApp = (bool) ($payment->checkoutDetails['from_app'] ?? false);

        // Web customers come back to the waiting page; app customers to a
        // page that needs no web login. Both re-check the status.
        $returnUrl = $fromApp
            ? route('payments.gateway.return', $payment->external_id)
            : route('user.payments.mobile.show', [$payment->order_id, $payment->id]);

        $billing = array_filter([
            'email_address' => (string) $user?->email,
            'phone_number' => $payment->phone ? '0'.substr($payment->phone, 3) : null,
            'country_code' => 'TZ',
            'first_name' => Str::limit($first, 50, ''),
            'last_name' => Str::limit($last, 50, ''),
        ], fn ($value) => filled($value));

        try {
            $response = $this->client()->post($this->url('/api/Transactions/SubmitOrderRequest'), [
                'id' => $payment->external_id,
                'currency' => 'TZS',
                'amount' => round((float) $payment->amount, 2),
                'description' => Str::limit('Order '.($payment->order?->order_number ?? $payment->order_id), 100, ''),
                'callback_url' => $returnUrl,
                'cancellation_url' => $fromApp ? route('payments.gateway.cancel', $payment->external_id) : $returnUrl,
                'notification_id' => $this->ipnId(),
                // On the website PesaPal's form sits in a frame on our own page;
                // when it finishes, our page (the parent) loads the return URL.
                'redirect_mode' => $fromApp ? 'TOP_WINDOW' : 'PARENT_WINDOW',
                'billing_address' => $billing,
            ]);
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach PesaPal. Please try again.', previous: $e);
        }

        $trackingId = $response->json('order_tracking_id');
        $redirect = $response->json('redirect_url');

        if (! $response->successful() || ! $this->ok($response->json()) || blank($trackingId) || ! $this->isPesaPalUrl($redirect)) {
            Log::warning('PesaPal order request failed', ['status' => $response->status(), 'error' => $response->json('error')]);

            throw new GatewayException(Str::limit('PesaPal could not start the payment.'.(($m = $this->errorMessage($response->json())) ? " ({$m})" : ''), 250));
        }

        $payment->update([
            'charged_amount' => $payment->amount,
            'charged_currency' => 'TZS',
            'payer_email' => $user?->email,
            'redirect_url' => $redirect,
        ]);

        return (string) $trackingId;
    }

    public function fetchStatus(GatewayPayment $payment): ?array
    {
        $pending = ['status' => 'pending', 'reference' => null, 'amount' => null];

        if (blank($payment->provider_transaction_id)) {
            return $pending;
        }

        try {
            $response = $this->client()->get($this->url('/api/Transactions/GetTransactionStatus'), [
                'orderTrackingId' => $payment->provider_transaction_id,
            ]);
        } catch (ConnectionException|GatewayException) {
            return $pending;
        }

        $data = $response->json() ?? [];

        // A failed or unpaid order comes back with "status": "500" and an
        // error object, so the status_code decides, not the envelope.
        if (! $response->successful() || ! is_array($data)) {
            return $pending;
        }

        // The answer must be about this exact order.
        if ((string) ($data['merchant_reference'] ?? '') !== (string) $payment->external_id) {
            Log::warning('PesaPal status for a different order', ['gateway_payment_id' => $payment->id]);

            return $pending;
        }

        $code = (int) ($data['status_code'] ?? 0);
        $reference = filled($data['confirmation_code'] ?? null) ? (string) $data['confirmation_code'] : null;

        // How the customer paid (e.g. MpesaTZ, Visa) and the number / masked
        // card PesaPal reports: shown as the payment proof.
        $paidWith = array_filter([
            'network' => filled($data['payment_method'] ?? null) ? Str::limit((string) $data['payment_method'], 60, '') : null,
            'phone' => blank($payment->phone) && filled($data['payment_account'] ?? null) ? Str::limit((string) $data['payment_account'], 60, '') : null,
        ]);
        if ($paidWith !== [] && in_array($code, [self::COMPLETED, self::FAILED], true)) {
            $payment->forceFill($paidWith)->save();
        }

        if ($code === self::COMPLETED && strcasecmp((string) ($data['payment_status_description'] ?? ''), 'Completed') === 0) {
            // Anything not paid in TZS is checked by an admin (amount 0 → underpaid).
            $amount = strtoupper((string) ($data['currency'] ?? '')) === 'TZS' ? (float) ($data['amount'] ?? 0) : 0.0;

            return ['status' => 'success', 'reference' => $reference, 'amount' => $amount];
        }

        if (in_array($code, [self::FAILED, self::REVERSED], true)) {
            return [
                'status' => 'failed',
                'reference' => $reference,
                'amount' => null,
                'message' => Str::limit((string) ($data['description'] ?? '') ?: 'PesaPal did not complete the payment.', 200),
            ];
        }

        return $pending;
    }

    /**
     * IPN (POST JSON) or a GET with query parameters. Never trusted on its
     * own: the status is always fetched from PesaPal.
     */
    public function parseCallback(Request $request): array
    {
        $reference = (string) ($request->input('OrderMerchantReference') ?? $request->query('OrderMerchantReference', ''));

        return [
            'external_id' => preg_match('/^[A-Za-z0-9]{8,50}$/', $reference) ? $reference : null,
            'status' => null,
            'reference' => null,
            'amount' => null,
            'verify' => true,
        ];
    }

    /**
     * PesaPal keeps resending an IPN until it gets this reply.
     */
    public function callbackResponse(Request $request, ?GatewayPayment $payment): array
    {
        return [
            'orderNotificationType' => (string) $request->input('OrderNotificationType', 'IPNCHANGE'),
            'orderTrackingId' => (string) $request->input('OrderTrackingId', ''),
            'orderMerchantReference' => (string) $request->input('OrderMerchantReference', ''),
            'status' => $payment ? 200 : 500,
        ];
    }

    public function testConnection(): void
    {
        $this->forgetToken();
        $this->token();
        $this->ipnId(force: true);
    }

    public function forgetToken(): void
    {
        Cache::forget('pesapal_token_'.$this->mode());
    }

    /**
     * The IPN registered for our callback URL, registered on first use and
     * again whenever the URL (callback token) or environment changes.
     */
    public function ipnId(bool $force = false): string
    {
        $url = $this->ipnUrl();
        $key = 'payment_gateway.pesapal.ipn_'.$this->mode();
        $saved = json_decode((string) Setting::get($key), true) ?: [];

        if (! $force && ($saved['url'] ?? null) === hash('sha256', $url) && filled($saved['id'] ?? null)) {
            return (string) $saved['id'];
        }

        try {
            $response = $this->client()->post($this->url('/api/URLSetup/RegisterIPN'), [
                'url' => $url,
                'ipn_notification_type' => 'POST',
            ]);
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach PesaPal. Please try again.', previous: $e);
        }

        $id = (string) $response->json('ipn_id');

        if (! $response->successful() || ! $this->ok($response->json()) || $id === '') {
            Log::warning('PesaPal IPN registration failed', ['status' => $response->status(), 'error' => $response->json('error')]);

            throw new GatewayException('PesaPal did not accept the notification (IPN) URL.');
        }

        // Only a hash of the URL is kept: the URL contains the callback secret.
        Setting::set($key, json_encode(['id' => $id, 'url' => hash('sha256', $url)]), 'string', PaymentGatewaySettingsService::GROUP);

        return $id;
    }

    /**
     * The saved IPN id for the admin panel (null until registered).
     */
    public function savedIpnId(): ?string
    {
        $saved = json_decode((string) Setting::get('payment_gateway.pesapal.ipn_'.$this->mode()), true) ?: [];

        return ($saved['url'] ?? null) === hash('sha256', $this->ipnUrl()) ? ($saved['id'] ?? null) : null;
    }

    public function ipnUrl(): string
    {
        return route('api.payments.callback', ['pesapal', app(PaymentGatewaySettingsService::class)->callbackToken()]);
    }

    public function mode(): string
    {
        return $this->config('environment') === 'live' ? 'live' : 'sandbox';
    }

    protected function client()
    {
        return Http::acceptJson()->asJson()->timeout(30)->withToken($this->token());
    }

    protected function token(): string
    {
        return Cache::remember('pesapal_token_'.$this->mode(), now()->addMinutes(4), function () {
            try {
                $response = Http::acceptJson()->asJson()->timeout(30)->post($this->url('/api/Auth/RequestToken'), [
                    'consumer_key' => (string) $this->config('consumer_key'),
                    'consumer_secret' => (string) $this->config('consumer_secret'),
                ]);
            } catch (ConnectionException $e) {
                throw new GatewayException('Could not reach PesaPal. Please try again.', previous: $e);
            }

            $token = (string) $response->json('token');

            if (! $response->successful() || ! $this->ok($response->json()) || $token === '') {
                // Never log the request: it carries the consumer secret.
                Log::error('PesaPal token request failed', ['status' => $response->status(), 'error' => $response->json('error')]);

                throw new GatewayException('PesaPal rejected the keys'.(($m = $this->errorMessage($response->json())) ? " ({$m})" : '').'.');
            }

            return $token;
        });
    }

    /**
     * PesaPal answers HTTP 200 with "status": "500" and an "error" object.
     */
    protected function ok(?array $data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        $status = (string) ($data['status'] ?? '200');

        return $status === '200' && empty($data['error']['code'] ?? null) && empty($data['error']['message'] ?? null);
    }

    protected function errorMessage(?array $data): string
    {
        $error = $data['error'] ?? null;
        $message = is_array($error) ? ($error['message'] ?? $error['code'] ?? '') : ($data['message'] ?? '');

        return is_string($message) ? Str::limit(trim($message), 150) : '';
    }

    protected function isPesaPalUrl(mixed $url): bool
    {
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'pesapal.com' || str_ends_with($host, '.pesapal.com');
    }

    protected function url(string $path): string
    {
        return rtrim((string) $this->config('urls.'.$this->mode()), '/').$path;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("payments.gateways.pesapal.{$key}", $default);
    }
}
