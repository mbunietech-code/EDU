<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['user:id,name,email', 'product:id,name', 'tool:id,name', 'plan:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($orders->items())->map(fn (Order $o) => $this->row($o))->all(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
                'pending' => Order::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'user:id,name,email',
            'product:id,name',
            'tool:id,name',
            'plan:id,name',
            'payments' => fn ($q) => $q->latest(),
        ]);

        return response()->json([
            'data' => array_merge($this->row($order), [
                'payment_instructions' => $order->payment_instructions,
                'rejection_reason' => $order->rejection_reason,
                'confirmed_at' => optional($order->confirmed_at)->toIso8601String(),
                'customer' => [
                    'name' => $order->user->name ?? '—',
                    'email' => $order->user->email ?? '—',
                ],
                'payments' => $order->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount_label' => 'TZS '.number_format((float) $p->amount),
                    'method' => $p->payment_method,
                    'status' => $p->status,
                    'reference' => $p->transaction_reference,
                    'created_ago' => optional($p->created_at)->diffForHumans(),
                ])->all(),
            ]),
        ]);
    }

    public function reject(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        if ($order->isConfirmed()) {
            return response()->json([
                'message' => 'This order is already confirmed. Revoke the subscription instead.',
            ], 422);
        }

        $order->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
        ]);

        ActivityLog::log('order_rejected', 'Order', $order->id, [
            'order_number' => $order->order_number,
            'reason' => $data['reason'],
        ]);

        return response()->json(['data' => $this->row($order->fresh()), 'message' => 'Order disapproved.']);
    }

    private function row(Order $o): array
    {
        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'title' => $o->product->name ?? $o->tool->name ?? 'Order',
            'customer_name' => $o->user->name ?? '—',
            'plan' => $o->plan->name ?? null,
            'amount_label' => 'TZS '.number_format((float) $o->amount),
            'status' => $o->status,
            'created_ago' => optional($o->created_at)->diffForHumans(),
            'created_at' => optional($o->created_at)->toIso8601String(),
        ];
    }
}
