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
    public function index(Request $request)
    {
        $orders = Order::with(['user', 'product', 'plan', 'tool', 'payment'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->input('search');
                $query->where('order_number', 'like', "%{$term}%")
                      ->orWhereHas('user', function ($q) use ($term) {
                          $q->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                      });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['user', 'product', 'plan', 'tool', 'payments.paymentProofs', 'payments.reviewer', 'subscription']);

        return view('admin.orders.show', compact('order'));
    }

    public function edit(Order $order)
    {
        return view('admin.orders.edit', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
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
