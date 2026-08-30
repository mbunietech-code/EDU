<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Member dashboard: personal stats + recent orders.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeSubs = $user->subscriptions()->where('status', 'active')->count();
        $pendingOrders = $user->orders()->where('status', 'pending')->count();
        $totalOrders = $user->orders()->count();
        $totalSpent = $user->payments()->where('status', 'approved')->sum('amount');

        $recent = $user->orders()
            ->with(['product:id,name', 'tool:id,name', 'plan:id,name'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Order $order) => [
                'title' => $this->orderTitle($order),
                'subtitle' => optional($order->created_at)->diffForHumans(),
                'status' => $order->status,
            ]);

        return response()->json([
            'stats' => [
                ['label' => 'Active subscriptions', 'value' => $activeSubs],
                ['label' => 'Pending orders', 'value' => $pendingOrders],
                ['label' => 'Total orders', 'value' => $totalOrders],
                ['label' => 'Total spent', 'value' => $this->money($totalSpent)],
            ],
            'recent' => $recent,
        ]);
    }

    /**
     * Admin dashboard: platform-wide metrics + recent orders/payments.
     */
    public function admin(Request $request): JsonResponse
    {
        $metrics = app(ReportService::class)->getDashboardMetrics();

        $recent = Order::with(['user:id,name', 'product:id,name', 'tool:id,name'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (Order $order) => [
                'title' => $this->orderTitle($order),
                'subtitle' => trim(($order->user->name ?? 'Unknown').' · '.optional($order->created_at)->diffForHumans()),
                'amount' => $this->money($order->amount),
            ]);

        return response()->json([
            'stats' => [
                ['label' => 'Users', 'value' => $metrics['total_users']],
                ['label' => 'Products', 'value' => $metrics['total_products']],
                ['label' => 'Active subscriptions', 'value' => $metrics['active_subscriptions']],
                ['label' => 'Expiring soon', 'value' => $metrics['expiring_soon']],
                ['label' => 'Pending payments', 'value' => $metrics['pending_payments']],
                ['label' => 'Available accounts', 'value' => $metrics['available_accounts']],
                [
                    'label' => 'Revenue this month',
                    'value' => $this->money($metrics['monthly_revenue']),
                ],
                ['label' => 'Assigned accounts', 'value' => $metrics['assigned_accounts']],
            ],
            'recent' => $recent,
        ]);
    }

    private function orderTitle(Order $order): string
    {
        return $order->product->name
            ?? $order->tool->name
            ?? $order->order_number
            ?? 'Order #'.$order->id;
    }

    private function money($amount): string
    {
        return 'TZS '.number_format((float) $amount);
    }
}
