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

    protected function render(Order $order)
    {
        abort_unless($order->isConfirmed(), 404);

        $order->load(['user', 'product', 'plan', 'tool']);

        $payment = $order->payments()->where('status', 'approved')->latest()->first();

        $pdf = Pdf::loadView('receipts.order', compact('order', 'payment'));

        return $pdf->download('Receipt-' . $order->order_number . '.pdf');
    }
}
