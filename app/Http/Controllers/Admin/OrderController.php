<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SoftwareAccessService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['user', 'product', 'plan', 'payment'])
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
        $order->load(['user', 'product', 'plan', 'payments.paymentProofs', 'payments.reviewer', 'subscription']);

        return view('admin.orders.show', compact('order'));
    }

    public function reopenAccess(SoftwareAccessService $service, Order $order)
    {
        if (! $order->isConfirmed() || $order->isSoftware() === false) {
            return back()->with('error', 'Software access can only be re-opened for a confirmed software order.');
        }

        $service->reopenForOrder($order);

        \App\Models\ActivityLog::log(
            'order_software_access_reopened',
            'Order',
            $order->id,
            ['order_number' => $order->order_number]
        );

        return back()->with('success', 'Software access re-opened for 20 minutes.');
    }
}