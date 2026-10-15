<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreOrderRequest;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Services\NotificationService;
use App\Services\OrderRenewalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index()
    {
        $orders = auth()->user()
            ->orders()
            ->with(['product', 'plan', 'tool', 'subscription'])
            ->withConfirmedRenewalsCount()
            ->latest()
            ->paginate(15);

        return view('user.orders.index', compact('orders'));
    }

    public function create(Product $product, ?Plan $plan = null)
    {
        if ($product->status !== 'published') {
            abort(404);
        }

        if (! $plan) {
            $plan = $product->plans()->active()->orderBy('sort_order')->first();
        }

        if (! $plan || $plan->product_id !== $product->id) {
            abort(404);
        }

        return view('user.orders.create', compact('product', 'plan'));
    }

    public function store(StoreOrderRequest $request)
    {
        $validated = $request->validated();

        $product = Product::findOrFail($validated['product_id']);
        $plan = Plan::where('id', $validated['plan_id'])
            ->where('product_id', $product->id)
            ->where('status', 'active')
            ->firstOrFail();

        if ($product->status !== 'published') {
            abort(404);
        }

        $order = Order::create([
            'user_id' => auth()->id(),
            'order_number' => $this->generateOrderNumber(),
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'status' => 'pending',
        ]);

        $this->notificationService->notifyOrderCreated(auth()->user(), $order);
        \App\Models\ActivityLog::log(
            'order_created',
            'Order',
            $order->id,
            ['order_number' => $order->order_number, 'amount' => $order->amount]
        );

        return redirect()->route('user.orders.show', $order)
            ->with('success', 'Order created. Please complete payment to activate your subscription.');
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['product', 'plan', 'tool.primaryDownload', 'payments.paymentProofs', 'subscription', 'renewsOrder']);

        $isOwner = $order->user_id === auth()->id();
        $renewPlans = $isOwner && $order->canBeRenewed()
            ? $order->product->plans()->active()->orderBy('sort_order')->get()
            : collect();
        $pendingRenewal = $isOwner ? $order->pendingRenewal() : null;
        $renewedBy = $order->isConfirmed() ? $order->confirmedRenewal() : null;

        return view('user.orders.show', compact('order', 'renewPlans', 'pendingRenewal', 'renewedBy'));
    }

    /**
     * Start a renewal: a new pending order for the same product (same plan
     * unless the customer picks another one) that points at this order. Paying
     * it extends the same subscription / VPN instead of starting a new one.
     */
    public function renew(Request $request, Order $order, OrderRenewalService $renewals)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        // Already started: send them to pay for that one instead of a duplicate.
        if ($pending = $order->pendingRenewal()) {
            return redirect()->route('user.payments.create', $pending)
                ->with('success', 'Your renewal is waiting for payment.');
        }

        $data = $request->validate(['plan_id' => ['nullable', 'integer']]);

        try {
            $renewal = $renewals->start($order, $data['plan_id'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('user.payments.create', $renewal)
            ->with('success', 'Renewal created. Pay to extend your access; the time is added to what you already have.');
    }

    public function cancel(Order $order)
    {
        $this->authorize('view', $order);

        if (! $order->canBeCancelledByCustomer()) {
            return back()->with('error', $order->isConfirmed()
                ? 'A confirmed order cannot be cancelled. Contact support if you need help.'
                : 'This order can no longer be cancelled.');
        }

        $order->update(['status' => 'cancelled']);

        \App\Models\ActivityLog::log('order_cancelled_by_customer', 'Order', $order->id, [
            'order_number' => $order->order_number,
        ]);

        return redirect()->route('user.orders.index')->with('success', 'Order cancelled.');
    }

    public function downloadSoftware(Order $order)
    {
        $this->authorize('view', $order);

        if (! $order->isSoftware() || ! $order->softwareAccessActive()) {
            return back()->with('error', 'Software download is locked. It opens for 20 minutes after payment is approved.');
        }

        $product = $order->product;

        if (! $product->software_file || ! Storage::disk(config('software.download_disk', 'private'))->exists($product->software_file)) {
            abort(404);
        }

        return Storage::disk(config('software.download_disk', 'private'))
            ->download($product->software_file, $product->software_filename ?: basename($product->software_file));
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'MBT-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
