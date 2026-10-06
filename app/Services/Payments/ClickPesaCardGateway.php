<?php

namespace App\Services\Payments;

use App\Models\GatewayPayment;
use App\Services\CurrencyRateService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

/**
 * ClickPesa card payments (Visa, Mastercard, Amex, UnionPay) on ClickPesa's
 * hosted card page. Uses the ClickPesa keys, token and webhook; charged in
 * USD, the only currency ClickPesa cards accept. Needs ClickPesa KYC.
 */
class ClickPesaCardGateway extends ClickPesaGateway
{
    public function __construct(protected CurrencyRateService $rates)
    {
    }

    public function key(): string
    {
        return 'clickpesa_card';
    }

    public function label(): string
    {
        return config('payments.gateways.clickpesa_card.label', 'Card (Visa / Mastercard)');
    }

    public function usesRedirect(): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        // Needs the ClickPesa keys, but not ClickPesa mobile money switched on.
        return (bool) config('payments.gateways.clickpesa_card.enabled')
            && filled($this->config('client_id'))
            && filled($this->config('api_key'));
    }

    public function currency(): string
    {
        return 'USD';
    }

    public function chargeFor(float $tzs): ?float
    {
        $converted = $this->rates->convert($tzs, $this->currency());

        return $converted ? max(0.01, round($converted, 2)) : null;
    }

    public function initiate(GatewayPayment $payment): ?string
    {
        $charge = $this->chargeFor((float) $payment->amount);

        if (! $charge) {
            throw new GatewayException('Card payments are temporarily unavailable. Please use another payment method.');
        }

        try {
            $response = $this->client()->post($this->url('/payments/initiate-card-payment'), $this->withChecksum([
                'amount' => number_format($charge, 2, '.', ''),
                'currency' => $this->currency(),
                'orderReference' => $payment->external_id,
                'customer' => [
                    'fullName' => (string) ($payment->checkoutDetails['name'] ?? $payment->user?->name),
                    'email' => (string) $payment->user?->email,
                    'phoneNumber' => $payment->phone,
                ],
            ]));
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach ClickPesa. Please try again.', previous: $e);
        }

        $link = $response->json('cardPaymentLink');

        if (! $response->successful() || blank($link)) {
            Log::warning('ClickPesa card payment failed', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

            throw GatewayException::fromResponse($response, 'Card payment could not be started.');
        }

        $payment->update([
            'charged_amount' => $charge,
            'charged_currency' => $this->currency(),
            'payer_email' => $payment->user?->email,
            'redirect_url' => $link,
        ]);

        return null;
    }
}
