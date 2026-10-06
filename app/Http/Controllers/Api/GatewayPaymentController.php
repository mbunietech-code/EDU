<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Payments\GatewayException;
use App\Services\Payments\GatewayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Automatic payments for the mobile app: mobile money push, ClickPesa card
 * and PayPal. Same rules as the web checkout; card and PayPal pages open in
 * the phone's browser and return to a page that needs no web login.
 */
class GatewayPaymentController extends Controller
{
    public function __construct(protected GatewayPaymentService $gateways)
    {
    }

    public function options(Request $request, Order $order): JsonResponse
    {
        $this->ownOrder($request, $order);

        $card = $this->gateways->gateway('clickpesa_card');
        $payPal = $this->gateways->gateway('paypal');
        $cardCharge = $card?->isEnabled() ? $card->chargeFor((float) $order->amount) : null;
        $payPalCharge = $payPal?->isEnabled() ? $payPal->chargeFor((float) $order->amount) : null;

        return response()->json(['data' => [
            'order_id' => $order->id,
            'amount' => (float) $order->amount,
            'amount_label' => 'TZS '.number_format((float) $order->amount),
            'mobile' => collect($this->gateways->mobile())->map(fn ($gateway, $key) => [
                'key' => $key,
                'label' => $gateway->label(),
                'networks' => collect($gateway->networks())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            ])->values()->all(),
            'card' => $cardCharge ? ['amount' => $cardCharge, 'currency' => $card->currency(), 'label' => $card->label()] : null,
            'paypal' => $payPalCharge ? ['amount' => $payPalCharge, 'currency' => $payPal->currency(), 'label' => $payPal->label()] : null,
            'manual' => PaymentMethod::enabled()->exists(),
            'active' => ($active = $this->gateways->activeFor($order)) ? $this->row($active) : null,
        ]]);
    }

    public function mobile(Request $request, Order $order): JsonResponse
    {
        $this->payableOrder($request, $order);
        $enabled = $this->gateways->mobile();

        $data = $request->validate([
            'gateway' => ['required', Rule::in(array_keys($enabled))],
            'phone' => ['required', 'string', 'max:20'],
            'network' => ['nullable', 'string', 'max:40'],
        ]);

        $gateway = $enabled[$data['gateway']];
        $networks = $gateway->networks();

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

        if (! $card?->isEnabled()) {
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

        if (! $payPal?->isEnabled()) {
            return response()->json(['message' => 'PayPal is not available right now.'], 422);
        }

        return $this->begin($request, $order, $payPal, '', null, ['from_app' => true]);
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
