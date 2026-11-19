<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\PaymentMethod;
use App\Services\Payments\GatewayException;
use App\Services\Payments\GatewayPaymentService;
use App\Services\Payments\PaymentChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Automatic payments for the mobile app: mobile money push, PesaPal, ClickPesa
 * card and PayPal. Same rules and channel order as the web checkout; provider
 * pages open inside the app and return to a page that needs no web login.
 * `channels` (priority order) is the current list; mobile / card / paypal
 * stay for older app versions.
 */
class GatewayPaymentController extends Controller
{
    public function __construct(
        protected GatewayPaymentService $gateways,
        protected PaymentChannelService $channels,
    ) {
    }

    public function options(Request $request, Order $order): JsonResponse
    {
        $this->ownOrder($request, $order);

        $available = $this->channels->available();
        $card = $this->gateways->gateway('clickpesa_card');
        $payPal = $this->gateways->gateway('paypal');
        $cardCharge = $available->contains('code', 'clickpesa_card') ? $card->chargeFor((float) $order->amount) : null;
        $payPalCharge = $available->contains('code', 'paypal') ? $payPal->chargeFor((float) $order->amount) : null;
        $manual = $available->contains('code', 'manual') && PaymentMethod::enabled()->exists();

        return response()->json(['data' => [
            'order_id' => $order->id,
            'amount' => (float) $order->amount,
            'amount_label' => 'TZS '.number_format((float) $order->amount),
            'channels' => $available
                ->map(fn (PaymentChannel $channel) => $this->channelRow($channel, $order, $cardCharge, $payPalCharge, $manual))
                ->filter()
                ->values()
                ->all(),
            'mobile' => collect($this->channels->pushGateways())->map(fn ($push, $key) => [
                'key' => $key,
                'label' => $push['gateway']->label(),
                'networks' => collect($push['networks'])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            ])->values()->all(),
            'card' => $cardCharge ? ['amount' => $cardCharge, 'currency' => $card->currency(), 'label' => $card->label()] : null,
            'paypal' => $payPalCharge ? ['amount' => $payPalCharge, 'currency' => $payPal->currency(), 'label' => $payPal->label()] : null,
            'manual' => $manual,
            'active' => ($active = $this->gateways->activeFor($order)) ? $this->row($active) : null,
        ]]);
    }

    public function mobile(Request $request, Order $order): JsonResponse
    {
        $this->payableOrder($request, $order);
        $enabled = $this->channels->pushGateways();

        $data = $request->validate([
            'gateway' => ['required', Rule::in(array_keys($enabled))],
            'phone' => ['required', 'string', 'max:20'],
            'network' => ['nullable', 'string', 'max:40'],
        ]);

        $gateway = $enabled[$data['gateway']]['gateway'];
        $networks = $enabled[$data['gateway']]['networks'];

        if ($networks && ! array_key_exists($data['network'] ?? '', $networks)) {
            return $this->invalid('network', 'Choose your mobile money network.');
        }

        $phone = GatewayPaymentService::normalizePhone($data['phone']);

        if (! $phone) {
            return $this->invalid('phone', 'Enter a valid Tanzanian mobile number, e.g. 0712 345 678.');
        }

        if ($active = $this->gateways->activeFor($order)) {
            return response()->json([
                'message' => 'A payment request was already sent. Complete it on your phone or wait for it to expire.',
                'data' => $this->row($active),
            ], 409);
        }

        return $this->begin($request, $order, $gateway, $phone, $networks ? $data['network'] : null);
    }

    public function card(Request $request, Order $order): JsonResponse
    {
        $this->payableOrder($request, $order);
        $card = $this->gateways->gateway('clickpesa_card');

        if (! $card?->isEnabled() || ! $this->channels->isAvailable('clickpesa_card')) {
            return response()->json(['message' => 'Card payments are not available right now.'], 422);
        }

        $data = $request->validate([
            'card_name' => ['required', 'string', 'max:120'],
            'card_phone' => ['required', 'string', 'max:20'],
        ], [], ['card_name' => 'name on card', 'card_phone' => 'phone number']);

        $phone = GatewayPaymentService::normalizePhone($data['card_phone']);

        if (! $phone) {
            return $this->invalid('card_phone', 'Enter a valid Tanzanian mobile number, e.g. 0712 345 678.');
        }

        return $this->begin($request, $order, $card, $phone, null, ['name' => trim($data['card_name'])]);
    }

    public function paypal(Request $request, Order $order): JsonResponse
    {
        $this->payableOrder($request, $order);
        $payPal = $this->gateways->gateway('paypal');

        if (! $payPal?->isEnabled() || ! $this->channels->isAvailable('paypal')) {
            return response()->json(['message' => 'PayPal is not available right now.'], 422);
        }

        return $this->begin($request, $order, $payPal, '', null, ['from_app' => true]);
    }

    /**
     * Pay through one entry of `channels` (any gateway).
     */
    public function channel(Request $request, Order $order): JsonResponse
    {
        $this->payableOrder($request, $order);

        $data = $request->validate([
            'channel' => ['required', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $channel = $this->channels->find($data['channel']);

        if (! $channel || $channel->flow() === 'manual') {
            return $this->invalid('channel', 'This payment option is not available right now. Please choose another.');
        }

        if ($channel->flow() === 'push' && ($active = $this->gateways->activeFor($order))) {
            return response()->json([
                'message' => 'A payment request was already sent. Complete it on your phone or wait for it to expire.',
                'data' => $this->row($active),
            ], 409);
        }

        try {
            $payment = $this->channels->start($order, $request->user(), $channel, $data, fromApp: true);
        } catch (GatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->row($payment->fresh()), 'message' => $channel->flow() === 'push'
            ? 'Payment request sent. Enter your PIN on your phone.'
            : 'Opening the secure payment page.'], 201);
    }

    public function status(Request $request, GatewayPayment $gatewayPayment): JsonResponse
    {
        abort_unless((int) $gatewayPayment->user_id === (int) $request->user()->id, 404);

        return response()->json(['data' => $this->row($this->gateways->refresh($gatewayPayment))]);
    }

    protected function begin(Request $request, Order $order, $gateway, string $phone, ?string $network, array $details = []): JsonResponse
    {
        try {
            $payment = $this->gateways->start($order, $request->user(), $gateway, $phone, $network, $details);
        } catch (GatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->row($payment->fresh()), 'message' => $gateway->usesRedirect()
            ? 'Opening the secure payment page.'
            : 'Payment request sent. Enter your PIN on your phone.'], 201);
    }

    protected function row(GatewayPayment $payment): array
    {
        $gateway = $this->gateways->gateway($payment->gateway);

        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'gateway' => $payment->gateway,
            'label' => $gateway?->label() ?? $payment->gateway,
            'uses_redirect' => (bool) $gateway?->usesRedirect(),
            'status' => $payment->status,
            'message' => $payment->message,
            'phone' => $payment->phone ?: null,
            'network' => $payment->network,
            'amount_label' => 'TZS '.number_format((float) $payment->amount),
            'charged_label' => $payment->charged_currency ? $payment->charged_currency.' '.number_format((float) $payment->charged_amount, 2) : null,
            'redirect_url' => $payment->isPending() ? $payment->redirect_url : null,
            'reference' => $payment->external_id,
            'timeout_minutes' => $this->gateways->timeoutMinutes($payment->gateway),
            'created_at' => optional($payment->created_at)->toIso8601String(),
        ];
    }

    /**
     * One checkout option for the app, or null when it cannot be offered for
     * this order (e.g. no exchange rate for a USD charge).
     */
    protected function channelRow(PaymentChannel $channel, Order $order, ?float $cardCharge, ?float $payPalCharge, bool $manual): ?array
    {
        $charge = match ($channel->flow()) {
            'card' => $cardCharge ? ['amount' => $cardCharge, 'currency' => 'USD'] : false,
            'paypal' => $payPalCharge ? ['amount' => $payPalCharge, 'currency' => $this->gateways->gateway('paypal')->currency()] : false,
            // WeChat Pay / Alipay have their own option (method checked in available()).
            'manual' => ($channel->network || $manual) ? [] : false,
            default => ['amount' => (float) $order->amount, 'currency' => 'TZS'],
        };

        if ($charge === false) {
            return null;
        }

        return [
            'code' => $channel->code,
            'name' => $channel->name,
            'kind' => $channel->kind,
            'flow' => $channel->flow(),
            'gateway' => $channel->gateway,
            'gateway_label' => $channel->gateway ? $this->gateways->gateway($channel->gateway)?->label() : null,
            'needs_phone' => $channel->needsPhone(),
            'needs_name' => $channel->needsName(),
            // Manual method to preselect (e.g. wechat_pay), for the proof upload.
            'payment_method' => $channel->flow() === 'manual' ? $channel->network : null,
            'amount' => $charge['amount'] ?? null,
            'currency' => $charge['currency'] ?? null,
        ];
    }

    protected function ownOrder(Request $request, Order $order): void
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
    }

    protected function payableOrder(Request $request, Order $order): void
    {
        $this->ownOrder($request, $order);

        if (! $order->isPending()) {
            abort(response()->json(['message' => 'This order is not awaiting payment.'], 422));
        }
    }

    protected function invalid(string $field, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
