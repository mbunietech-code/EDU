<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['product:id,name,slug', 'tool:id,name,slug', 'plan:id,name'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($orders->items())->map(fn (Order $o) => $this->row($o))->all(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load(['product:id,name,slug', 'tool:id,name,slug', 'plan:id,name', 'payments:id,order_id,amount,status,created_at']);

        return response()->json([
            'data' => array_merge($this->row($order), [
                'payment_instructions' => $order->payment_instructions,
                'rejection_reason' => $order->rejection_reason,
                'confirmed_at' => optional($order->confirmed_at)->toIso8601String(),
                'software_access_expires_at' => optional($order->software_access_expires_at)->toIso8601String(),
                'payments' => $order->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount_label' => 'TZS '.number_format((float) $p->amount),
                    'status' => $p->status,
                    'created_at' => optional($p->created_at)->toIso8601String(),
                ])->all(),
            ]),
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if (! $order->canBeCancelledByCustomer()) {
            return response()->json([
                'message' => $order->isConfirmed()
                    ? 'A confirmed order cannot be cancelled.'
                    : 'This order can no longer be cancelled.',
            ], 422);
        }

        $order->update(['status' => 'cancelled']);
        \App\Models\ActivityLog::log('order_cancelled_by_customer', 'Order', $order->id, [
            'order_number' => $order->order_number,
        ]);

        return response()->json(['data' => $this->row($order->fresh())]);
    }

    private function row(Order $o): array
    {
        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'title' => $o->product->name ?? $o->tool->name ?? 'Order',
            'plan' => $o->plan->name ?? null,
            'amount' => (float) $o->amount,
            'amount_label' => 'TZS '.number_format((float) $o->amount),
            'status' => $o->status,
            'can_cancel' => $o->canBeCancelledByCustomer(),
            'created_at' => optional($o->created_at)->toIso8601String(),
            'created_ago' => optional($o->created_at)->diffForHumans(),
        ];
    }
}
