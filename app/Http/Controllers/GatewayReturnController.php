<?php

namespace App\Http\Controllers;

use App\Models\GatewayPayment;
use App\Services\Payments\GatewayPaymentService;

/**
 * Where PayPal sends app customers back to. Runs in the phone's browser,
 * which has no web login, so the payment is found by its random reference.
 * It only confirms or cancels that payment and shows the result.
 */
class GatewayReturnController extends Controller
{
    public function __construct(protected GatewayPaymentService $gateways)
    {
    }

    public function return(string $reference)
    {
        $payment = $this->gateways->refresh($this->find($reference), force: true);

        return view('payments.gateway-return', ['payment' => $payment, 'cancelled' => false]);
    }

    public function cancel(string $reference)
    {
        $payment = $this->find($reference);

        if ($payment->isPending()) {
            $this->gateways->markFailed($payment, 'Cancelled on '.($this->gateways->gateway($payment->gateway)?->label() ?? 'the payment page').'.');
        }

        return view('payments.gateway-return', ['payment' => $payment->fresh(), 'cancelled' => true]);
    }

    protected function find(string $reference): GatewayPayment
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{8,40}$/', $reference), 404);

        return GatewayPayment::where('external_id', $reference)->firstOrFail();
    }
}
