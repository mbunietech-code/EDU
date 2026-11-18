<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\GatewayException;
use App\Services\Payments\GatewayPaymentService;
use App\Services\Payments\PaymentChannelService;
use Illuminate\Http\Request;

/**
 * Pay an order through the channel the customer picked at checkout
 * (M-Pesa via ClickPesa, card via PesaPal, ...). Everything happens on our
 * own pages: a USSD push, or the provider's form shown inside checkout.
 */
class ChannelPaymentController extends Controller
{
    public function __construct(
        protected GatewayPaymentService $gateways,
        protected PaymentChannelService $channels,
    ) {
    }

    public function store(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPending()) {
            return redirect()->route('user.orders.show', $order)->with('error', 'This order is no longer pending payment.');
        }

        $validated = $request->validate([
            'channel' => ['required', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $channel = $this->channels->find($validated['channel']);

        if (! $channel || $channel->flow() === 'manual') {
            return back()->withInput()->with('error', 'This payment option is not available right now. Please choose another.');
        }

        if ($channel->flow() === 'push' && ($active = $this->gateways->activeFor($order))) {
            return redirect()->route('user.payments.mobile.show', [$order, $active])
                ->with('error', 'A payment request was already sent. Complete it on your phone or wait for it to expire.');
        }

        try {
            $payment = $this->channels->start($order, $request->user(), $channel, $validated);
        } catch (GatewayException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $payment = $payment->fresh();

        // PayPal does not allow its page inside another site.
        if ($channel->flow() === 'paypal' && $payment->redirect_url) {
            return redirect()->away($payment->redirect_url);
        }

        // Push prompt, or the provider's form inside our waiting page.
        return redirect()->route('user.payments.mobile.show', [$order, $payment]);
    }
}
