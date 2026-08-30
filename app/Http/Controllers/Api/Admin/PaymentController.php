<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\PaymentApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentApprovalService $approvals,
        protected NotificationService $notifications,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $payments = Payment::with(['user:id,name,email', 'order.product:id,name', 'order.tool:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($payments->items())->map(fn (Payment $p) => $this->row($p))->all(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
                'pending' => Payment::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['user:id,name,email', 'order.product:id,name', 'order.plan:id,name', 'paymentProofs', 'reviewer:id,name']);

        return response()->json([
            'data' => array_merge($this->row($payment), [
                'reference' => $payment->transaction_reference,
                'admin_note' => $payment->admin_note,
                'reviewed_by' => $payment->reviewer->name ?? null,
                'customer' => [
                    'name' => $payment->user->name ?? '—',
                    'email' => $payment->user->email ?? '—',
                ],
                'order' => [
                    'id' => $payment->order->id,
                    'order_number' => $payment->order->order_number,
                    'status' => $payment->order->status,
                ],
                'proofs' => $payment->paymentProofs->map(fn ($proof) => [
                    'id' => $proof->id,
                    'path' => "/admin/payments/{$payment->id}/proofs/{$proof->id}",
                    'caption' => $proof->caption,
                ])->all(),
            ]),
        ]);
    }

    public function approve(Payment $payment): JsonResponse
    {
        if (! $payment->isPending()) {
            return response()->json(['message' => 'Payment is not pending review.'], 422);
        }

        try {
            $this->approvals->approve($payment);
        } catch (\RuntimeException $e) {
            report($e);

            return response()->json([
                'message' => 'Payment review started but no available account exists for the product.',
            ], 422);
        }

        $this->notifications->notifyPaymentApproved($payment->user, $payment->fresh());

        $order = $payment->order;
        if ($order->isToolOrder()) {
            $this->notifications->notifyToolKeyDelivered($payment->user, $order);
        } elseif ($order->isSoftware()) {
            $this->notifications->notifySoftwareDelivered($payment->user, $order);
        } else {
            $this->notifications->notifySubscriptionActivated($payment->user, $order->subscription);
        }

        return response()->json(['data' => $this->row($payment->fresh()), 'message' => 'Payment approved.']);
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        if (! $payment->isPending()) {
            return response()->json(['message' => 'Payment is not pending review.'], 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->approvals->reject($payment, $data['reason'] ?? null);
        $this->notifications->notifyPaymentRejected($payment->user, $payment->fresh());

        return response()->json(['data' => $this->row($payment->fresh()), 'message' => 'Payment rejected.']);
    }

    public function proof(Payment $payment, string $proofId): StreamedResponse
    {
        $proof = $payment->paymentProofs()->findOrFail($proofId);

        abort_unless(Storage::disk('private')->exists($proof->image_path), 404);

        return Storage::disk('private')->download($proof->image_path);
    }

    private function row(Payment $p): array
    {
        return [
            'id' => $p->id,
            'order_id' => $p->order_id,
            'title' => $p->order?->product?->name ?? $p->order?->tool?->name ?? 'Payment',
            'customer_name' => $p->user->name ?? '—',
            'amount_label' => 'TZS '.number_format((float) $p->amount),
            'method' => $p->payment_method,
            'status' => $p->status,
            'created_ago' => optional($p->created_at)->diffForHumans(),
            'created_at' => optional($p->created_at)->toIso8601String(),
        ];
    }
}
