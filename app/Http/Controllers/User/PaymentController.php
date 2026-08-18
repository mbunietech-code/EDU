<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UploadPaymentProofRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index()
    {
        $payments = auth()->user()
            ->payments()
            ->with(['order.product', 'paymentProofs'])
            ->latest()
            ->paginate(15);

        return view('user.payments.index', compact('payments'));
    }

    public function show(Payment $payment)
    {
        $this->authorize('view', $payment);

        $payment->load(['order.product', 'order.plan', 'paymentProofs']);

        return view('user.payments.show', compact('payment'));
    }

    public function create(Order $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPending()) {
            return redirect()->route('user.orders.show', $order)
                ->with('error', 'This order is no longer pending payment.');
        }

        $paymentMethods = \App\Models\PaymentMethod::enabled()
            ->orderBy('sort_order')
            ->get();

        return view('user.payments.create', compact('order', 'paymentMethods'));
    }

    public function store(UploadPaymentProofRequest $request, Order $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPending()) {
            return redirect()->route('user.orders.show', $order)
                ->with('error', 'This order is already paid or confirmed.');
        }

        $validated = $request->validated();

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'transaction_reference' => $validated['transaction_reference'],
            'status' => 'pending',
        ]);

        $path = $request->file('payment_proof')
            ->store('payment-proofs', 'private');

        $payment->paymentProofs()->create([
            'image_path' => $path,
            'caption' => $validated['note'] ?? null,
        ]);

        $this->notificationService->notifyPaymentSubmitted(auth()->user(), $payment);
        $this->notificationService->notifyAdminNewPaymentProof($payment);

        return redirect()->route('user.orders.show', $order)
            ->with('success', 'Payment proof submitted. Awaiting admin review.');
    }

    public function showProof(Payment $payment, string $proofId)
    {
        $this->authorize('view', $payment);

        $proof = $payment->paymentProofs()->findOrFail($proofId);

        if (! Storage::disk('private')->exists($proof->image_path)) {
            abort(404);
        }

        return Storage::disk('private')->download($proof->image_path);
    }
}