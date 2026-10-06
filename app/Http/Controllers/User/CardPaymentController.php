<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\GatewayException;
use App\Services\Payments\GatewayPaymentService;
use Illuminate\Http\Request;

/**
 * Pay an order by card on ClickPesa's secure hosted card page.
 */
class CardPaymentController extends Controller
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

        $card = $this->gateways->gateway('clickpesa_card');

        if (! $card?->isEnabled()) {
            return back()->with('error', 'Card payments are not available right now.');
        }

        $validated = $request->validate([
            'card_name' => ['required', 'string', 'max:120'],
            'card_phone' => ['required', 'string', 'max:20'],
        ], [], ['card_name' => 'name on card', 'card_phone' => 'phone number']);

        $phone = GatewayPaymentService::normalizePhone($validated['card_phone']);

        if (! $phone) {
            return back()->withInput()->withErrors(['card_phone' => 'Enter a valid Tanzanian mobile number, e.g. 0712 345 678.']);
        }

        try {
            $payment = $this->gateways->start($order, $request->user(), $card, $phone, null, ['name' => trim($validated['card_name'])]);
        } catch (GatewayException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // The waiting page links to the card page and confirms automatically.
        return redirect()->route('user.payments.mobile.show', [$order, $payment]);
    }
}
