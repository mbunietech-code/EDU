<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Services\Payments\GatewayException;
use App\Services\Payments\GatewayPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pay an order instantly by mobile money push (AzamPay / ClickPesa).
 */
class MobilePaymentController extends Controller
{
    public function __construct(protected GatewayPaymentService $gateways)
    {
    }

    public function store(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPending()) {
            return redirect()->route('user.orders.show', $order)->with('error', 'This order is no longer pending payment.');
        }

        $enabled = $this->gateways->mobile();

        $validated = $request->validate([
            'gateway' => ['required', Rule::in(array_keys($enabled))],
            'phone' => ['required', 'string', 'max:20'],
            'network' => ['nullable', 'string', 'max:40'],
        ]);

        $gateway = $enabled[$validated['gateway']];
        $networks = $gateway->networks();

        if ($networks && ! array_key_exists($validated['network'] ?? '', $networks)) {
            return back()->withInput()->withErrors(['network' => 'Choose your mobile money network.']);
        }

        $phone = GatewayPaymentService::normalizePhone($validated['phone']);

        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => 'Enter a valid Tanzanian mobile number, e.g. 0712 345 678.']);
        }

        if ($active = $this->gateways->activeFor($order)) {
            return redirect()->route('user.payments.mobile.show', [$order, $active])
                ->with('error', 'A payment request was already sent. Complete it on your phone or wait for it to expire.');
        }

        try {
            $payment = $this->gateways->start($order, $request->user(), $gateway, $phone, $networks ? $validated['network'] : null);
        } catch (GatewayException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('user.payments.mobile.show', [$order, $payment]);
    }

    public function show(Order $order, GatewayPayment $gatewayPayment)
    {
        $this->authorizePayment($order, $gatewayPayment);

        $gatewayLabel = $this->gateways->gateway($gatewayPayment->gateway)?->label() ?? $gatewayPayment->gateway;

        return view('user.payments.mobile', [
            'order' => $order,
            'gatewayPayment' => $gatewayPayment,
            'gatewayLabel' => $gatewayLabel,
            'timeoutMinutes' => $this->gateways->timeoutMinutes($gatewayPayment->gateway),
        ]);
    }

    public function status(Order $order, GatewayPayment $gatewayPayment)
    {
        $this->authorizePayment($order, $gatewayPayment);

        // Ask the provider at most every 10 seconds; callbacks usually arrive first.
        if (! $gatewayPayment->isFinal() && (! $gatewayPayment->last_checked_at || $gatewayPayment->last_checked_at->lt(now()->subSeconds(10)))) {
            $gatewayPayment = $this->gateways->refresh($gatewayPayment);
        }

        return response()->json([
            'status' => $gatewayPayment->status,
            'message' => $gatewayPayment->message,
            'redirect' => $gatewayPayment->isSuccessful() ? route('user.orders.show', $order) : null,
        ]);
    }

    protected function authorizePayment(Order $order, GatewayPayment $gatewayPayment): void
    {
        $this->authorize('view', $order);

        abort_unless((int) $gatewayPayment->order_id === (int) $order->id && (int) $gatewayPayment->user_id === (int) auth()->id(), 404);
    }
}
