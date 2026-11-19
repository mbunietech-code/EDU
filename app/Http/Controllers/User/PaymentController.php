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

        $payment->load(['order.product', 'order.plan', 'paymentProofs', 'gatewayPayment']);

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
            ->whereNotNull('qr_image')
            ->orderBy('sort_order')
            ->get();

        $gatewayService = app(\App\Services\Payments\GatewayPaymentService::class);
        $channelService = app(\App\Services\Payments\PaymentChannelService::class);

        // Every way to pay, in the order set in Admin → Payment methods.
        // WeChat Pay / Alipay have their own option; "Pay manually" lists the rest.
        $ownCodes = $channelService->ownOptionMethodCodes();
        $channelOptions = [];
        foreach ($channelService->available() as $channel) {
            $flow = $channel->flow();
            $charge = null;

            if ($flow === 'manual') {
                $methods = filled($channel->network)
                    ? $paymentMethods->where('code', $channel->network)->values()
                    : $paymentMethods->whereNotIn('code', $ownCodes)->values();

                if ($methods->isEmpty()) {
                    continue;
                }

                $own = filled($channel->network);
                $channelOptions[$channel->code] = [
                    'code' => $channel->code,
                    'name' => $own ? $methods->first()->name : $channel->name,
                    'kind' => 'manual',
                    'flow' => 'manual',
                    'via' => null,
                    'needs_phone' => false,
                    'charge' => null,
                    'methods' => $methods,
                    'own' => $own,
                ];

                continue;
            }

            if (in_array($flow, ['card', 'paypal'], true)) {
                $driver = $gatewayService->gateway($channel->gateway);
                $amount = $driver->chargeFor((float) $order->amount);

                if (! $amount) {
                    continue; // No exchange rate today.
                }

                $charge = ['amount' => $amount, 'currency' => $driver->currency()];
            }

            $channelOptions[$channel->code] = [
                'code' => $channel->code,
                'name' => $channel->name,
                'kind' => $channel->kind,
                'flow' => $flow,
                'via' => $channel->gateway ? ($gatewayService->gateway($channel->gateway)?->label() ?? $channel->gateway) : null,
                'needs_phone' => $channel->needsPhone(),
                'charge' => $charge,
            ];
        }

        return view('user.payments.create', compact('order', 'paymentMethods', 'channelOptions'));
    }

    public function store(UploadPaymentProofRequest $request, Order $order)
    {
        $this->authorize('view', $order);

        if (! $order->isPending()) {
            return redirect()->route('user.orders.show', $order)
                ->with('error', 'This order is already paid or confirmed.');
        }

        if ($order->payments()->where('status', 'pending')->exists()) {
            return redirect()->route('user.orders.show', $order)
                ->with('error', 'You already have a payment awaiting review.');
        }

        $validated = $request->validated();

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'amount' => $validated['amount'] ?? $order->amount,
            'payment_method' => $validated['payment_method'],
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'status' => 'pending',
        ]);

        $path = app(\App\Services\PaymentProofImageService::class)->store($request->file('payment_proof'));

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

        return Storage::disk('private')->response($proof->image_path);
    }
}
