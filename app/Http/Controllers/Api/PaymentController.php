<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = $request->user()
            ->payments()
            ->with(['order.product:id,name', 'order.tool:id,name'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($payments->items())->map(fn (Payment $p) => $this->row($p))->all(),
            'meta' => ['current_page' => $payments->currentPage(), 'last_page' => $payments->lastPage()],
        ]);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);
        $payment->load(['order.product:id,name', 'order.plan:id,name', 'paymentProofs']);

        return response()->json([
            'data' => array_merge($this->row($payment), [
                'transaction_reference' => $payment->transaction_reference,
                'admin_note' => $payment->admin_note,
                'proofs_count' => $payment->paymentProofs->count(),
            ]),
        ]);
    }

    public function methods(): JsonResponse
    {
        $methods = PaymentMethod::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $methods->map(fn ($m) => [
                'code' => $m->code,
                'name' => $m->name,
                'description' => $m->description,
                'account_number' => $m->account_number,
                'instructions' => $m->instructions,
                'qr_image_url' => $m->qr_image ? asset('storage/'.$m->qr_image) : null,
            ])->all(),
        ]);
    }

    public function store(Request $request, Order $order, NotificationService $notifications): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if (! $order->isPending()) {
            return response()->json(['message' => 'This order is not awaiting payment.'], 422);
        }
        if ($order->payments()->where('status', 'pending')->exists()) {
            return response()->json(['message' => 'You already have a payment awaiting review.'], 422);
        }

        $data = $request->validate([
            'payment_method' => ['required', 'string', Rule::exists('payment_methods', 'code')],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_proof' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'amount' => $data['amount'] ?? $order->amount,
            'payment_method' => $data['payment_method'],
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'status' => 'pending',
        ]);

        $path = app(\App\Services\PaymentProofImageService::class)->store($request->file('payment_proof'));
        $payment->paymentProofs()->create(['image_path' => $path, 'caption' => $data['note'] ?? null]);

        $notifications->notifyPaymentSubmitted($request->user(), $payment);
        $notifications->notifyAdminNewPaymentProof($payment);

        return response()->json(['data' => $this->row($payment->fresh()), 'message' => 'Payment submitted. Awaiting review.']);
    }

    private function row(Payment $p): array
    {
        return [
            'id' => $p->id,
            'order_id' => $p->order_id,
            'title' => $p->order?->product?->name ?? $p->order?->tool?->name ?? 'Payment',
            'amount_label' => 'TZS '.number_format((float) $p->amount),
            'method' => $p->payment_method,
            'status' => $p->status,
            'created_ago' => optional($p->created_at)->diffForHumans(),
            'created_at' => optional($p->created_at)->toIso8601String(),
        ];
    }
}
