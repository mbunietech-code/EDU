<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\DeletionService;
use App\Services\SoftwareAccessService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /** Status filter values: stored statuses plus the computed ones admins see on badges. */
    public const STATUS_FILTERS = Order::STATUS_FILTERS;

    public const SORTS = ['newest', 'oldest', 'amount_desc', 'amount_asc'];

    public function index(Request $request)
    {
        $renewalsReady = Order::renewalsReady();
        $f = $request->only(['search', 'status', 'user', 'product', 'plan', 'device', 'type', 'sort']);

        $orders = Order::with(array_merge(
                ['user', 'product', 'plan', 'tool', 'payment', 'subscription'],
                $renewalsReady ? ['renewsOrder:id,order_number'] : []
            ))
            ->withConfirmedRenewalsCount()
            ->when($f['search'] ?? null, function ($query, $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('order_number', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->when(in_array($f['status'] ?? null, self::STATUS_FILTERS, true), fn ($q) => $q->filterStatus($f['status']))
            ->when(ctype_digit((string) ($f['user'] ?? '')), fn ($q) => $q->where('user_id', $f['user']))
            ->when(ctype_digit((string) ($f['product'] ?? '')), fn ($q) => $q->where('product_id', $f['product']))
            ->when(ctype_digit((string) ($f['plan'] ?? '')), fn ($q) => $q->where('plan_id', $f['plan']))
            ->when(($f['device'] ?? '') !== '', fn ($q) => $f['device'] === 'none'
                ? $q->where(fn ($d) => $d->whereNull('device')->orWhere('device', ''))
                : $q->where('device', $f['device']))
            ->when($renewalsReady && ($f['type'] ?? null) === 'renewal', fn ($q) => $q->whereNotNull('renews_order_id'))
            ->when($renewalsReady && ($f['type'] ?? null) === 'new', fn ($q) => $q->whereNull('renews_order_id'))
            ->when(true, fn ($q) => match ($f['sort'] ?? 'newest') {
                'oldest' => $q->oldest(),
                'amount_desc' => $q->orderByDesc('amount')->latest(),
                'amount_asc' => $q->orderBy('amount')->latest(),
                default => $q->latest(),
            })
            ->paginate(15)
            ->withQueryString();

        $products = \App\Models\Product::orderBy('name')->get(['id', 'name']);
        $plans = ctype_digit((string) ($f['product'] ?? ''))
            ? \App\Models\Plan::where('product_id', $f['product'])->orderBy('sort_order')->get(['id', 'name'])
            : collect();
        $devices = Order::whereNotNull('device')->where('device', '!=', '')->distinct()->orderBy('device')->pluck('device');
        $filterUser = ctype_digit((string) ($f['user'] ?? '')) ? \App\Models\User::find($f['user'], ['id', 'name']) : null;

        return view('admin.orders.index', compact('orders', 'products', 'plans', 'devices', 'filterUser', 'renewalsReady'));
    }

    public function show(Order $order)
    {
        $order->load(['user', 'product', 'plan', 'tool', 'payments.paymentProofs', 'payments.reviewer', 'subscription']);
        $renewedBy = $order->isConfirmed() ? $order->confirmedRenewal() : null;
        $pendingRenewal = $order->pendingRenewal();

        return view('admin.orders.show', compact('order', 'renewedBy', 'pendingRenewal'));
    }

    public function edit(Order $order)
    {
        return view('admin.orders.edit', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
            'device' => ['nullable', 'string', 'max:100'],
        ]);

        $order->update($validated);

        ActivityLog::log('order_updated', 'Order', $order->id, ['order_number' => $order->order_number]);

        return redirect()->route('admin.orders.show', $order)->with('success', 'Order updated.');
    }

    public function reject(Request $request, Order $order)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        if ($order->isConfirmed()) {
            return back()->with('error', 'This order is already confirmed and cannot be disapproved. Revoke the subscription instead.');
        }

        $order->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['reason'],
        ]);

        ActivityLog::log('order_rejected', 'Order', $order->id, ['order_number' => $order->order_number, 'reason' => $validated['reason']]);

        return back()->with('success', 'Order disapproved.');
    }

    public function destroy(Request $request, Order $order, DeletionService $deletionService)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $deletionService->delete($order, $validated['reason']);

        return redirect()->route('admin.orders.index')->with('success', 'Order deleted.');
    }

    public function reopenAccess(SoftwareAccessService $service, Order $order)
    {
        if (! $order->isConfirmed() || $order->isSoftware() === false) {
            return back()->with('error', 'Software access can only be re-opened for a confirmed software order.');
        }

        $service->reopenForOrder($order);

        ActivityLog::log(
            'order_software_access_reopened',
            'Order',
            $order->id,
            ['order_number' => $order->order_number]
        );

        return back()->with('success', 'Software access re-opened for 20 minutes.');
    }
}
