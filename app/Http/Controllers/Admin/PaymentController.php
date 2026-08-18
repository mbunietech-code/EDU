<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\PaymentApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentApprovalService $paymentApprovalService,
        protected NotificationService $notificationService
    ) {
    }

    public function index(Request $request)
    {
        $pendingCount = Payment::where('status', 'pending')->count();

        $payments = Payment::with(['user', 'order.product'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.payments.index', compact('payments', 'pendingCount'));
    }

    public function show(Payment $payment)
    {
        $payment->load(['user', 'order.product', 'order.plan', 'paymentProofs', 'reviewer']);

        return view('admin.payments.show', compact('payment'));
    }

    public function approve(Payment $payment)
    {
        $this->authorize('approve', $payment);

        if (! $payment->isPending()) {
            return back()->with('error', 'Payment is not pending review.');
        }

        try {
            $this->paymentApprovalService->approve($payment);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            $product = $payment->order->product;

            if ($message === 'no available product key') {
                $this->notificationService->notifyAdminAccountUnavailable($product);
                report($e);

                \App\Models\ActivityLog::log(
                    'payment_approval_failed',
                    'Payment',
                    $payment->id,
                    ['reason' => $message]
                );

                return back()->with('error', 'Payment reviewed but no available product key exists for this software. Add product keys for the product.');
            }

            $this->notificationService->notifyAdminAccountUnavailable($product);
            report($e);

            \App\Models\ActivityLog::log(
                'payment_approval_failed',
                'Payment',
                $payment->id,
                ['reason' => $message]
            );

            return back()->with('error', 'Payment review started but no available account exists for the product. An account is required to activate the subscription.');
        }

        $this->notificationService->notifyPaymentApproved($payment->user, $payment->fresh());

        $order = $payment->order;

        if ($order->isSoftware()) {
            $this->notificationService->notifySoftwareDelivered($payment->user, $order);
        } else {
            $this->notificationService->notifySubscriptionActivated($payment->user, $order->subscription);
        }

        return back()->with('success', 'Payment approved and ' . ($order->isSoftware() ? 'software delivered.' : 'subscription activated.'));
    }

    public function reject(Request $request, Payment $payment)
    {
        $this->authorize('reject', $payment);

        if (! $payment->isPending()) {
            return back()->with('error', 'Payment is not pending review.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->paymentApprovalService->reject($payment, $validated['reason'] ?? null);
        $this->notificationService->notifyPaymentRejected($payment->user, $payment->fresh());

        return back()->with('success', 'Payment rejected.');
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