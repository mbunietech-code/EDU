<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreOrderRequest;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Services\NotificationService;
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
            ->with(['product', 'plan'])
            ->latest()
            ->paginate(15);

        return view('user.orders.index', compact('orders'));
    }

    public function create(Product $product, Plan $plan = null)
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

        $order->load(['product', 'plan', 'payments.paymentProofs', 'subscription']);

        return view('user.orders.show', compact('order'));
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'MBT-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}