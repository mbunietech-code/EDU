<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Services\Payments\GatewayException;
use App\Services\Payments\GatewayPaymentService;
use Illuminate\Http\Request;

/**
 * Pay an order on PayPal's page (PayPal balance or card), then come back here.
 */
class PayPalPaymentController extends Controller
{
    public function __construct(protected GatewayPaymentService $gateways)
    {
    }

    public function start(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPending()) {
            return redirect()->route('user.orders.show', $order)->with('error', 'This order is no longer pending payment.');
        }

        $payPal = $this->gateways->gateway('paypal');

        if (! $payPal?->isEnabled()) {
            return back()->with('error', 'PayPal is not available right now.');
        }

        try {
            $payment = $this->gateways->start($order, $request->user(), $payPal, '', null);
        } catch (GatewayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($payment->fresh()->redirect_url);
    }

    public function return(Request $request, Order $order)
    {
        $payment = $this->findForReturn($request, $order);
        $payment = $this->gateways->refresh($payment, force: true);

        if ($payment->isSuccessful()) {
            return redirect()->route('user.orders.show', $order)->with('success', 'Payment received through PayPal. Thank you!');
        }

        if ($payment->status === 'failed') {
            return redirect()->route('user.payments.create', [$order, 'tab' => 'paypal'])->with('error', 'PayPal did not complete the payment. Please try again.');
        }

        // Approved but not settled yet: wait on the status page.
        return redirect()->route('user.payments.mobile.show', [$order, $payment]);
    }

    public function cancel(Request $request, Order $order)
    {
        $payment = $this->findForReturn($request, $order);

        if ($payment->isPending()) {
            $this->gateways->markFailed($payment, 'Cancelled on PayPal.');
        }

        return redirect()->route('user.payments.create', [$order, 'tab' => 'paypal'])->with('error', 'PayPal payment was cancelled. You can try again or choose another method.');
    }

    protected function findForReturn(Request $request, Order $order): GatewayPayment
    {
        $this->authorize('view', $order);

        return GatewayPayment::where('order_id', $order->id)
            ->where('user_id', auth()->id())
            ->where('gateway', 'paypal')
            ->where('provider_transaction_id', (string) $request->query('token'))
            ->firstOrFail();
    }
}
