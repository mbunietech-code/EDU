<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function userShow(Order $order)
    {
        $this->authorize('view', $order);

        return $this->render($order);
    }

    public function adminShow(Order $order)
    {
        return $this->render($order);
    }

    /**
     * Signed, session-independent download — used by the mobile app, which
     * opens this URL directly in the device's browser (no bearer-token
     * header support there). The signature itself proves authorization,
     * since it's only ever generated server-side after the API layer has
     * already checked the caller may see this order.
     */
    public function signedShow(Order $order)
    {
        return $this->render($order);
    }

    protected function render(Order $order)
    {
        abort_unless($order->isConfirmed(), 404);

        $order->load(['user', 'product', 'plan', 'tool']);

        $payment = $order->payments()->where('status', 'approved')->latest()->first();

        $pdf = Pdf::loadView('receipts.order', compact('order', 'payment'));

        return $pdf->download('Receipt-' . $order->order_number . '.pdf');
    }
}
