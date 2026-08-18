<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Account;
use App\Models\Order;
use App\Models\Payment;

class ReportService
{
    public function getRevenueReport(int $months = 12): array
    {
        $startDate = now(config('app.timezone'))->subMonths($months)->startOfMonth();

        $revenue = Payment::where('status', 'approved')
            ->whereDate('created_at', '>=', $startDate)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return $revenue->toArray();
    }

    public function getOrdersReport(int $months = 12): array
    {
        $startDate = now(config('app.timezone'))->subMonths($months)->startOfMonth();

        $orders = Order::whereDate('created_at', '>=', $startDate)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total, status')
            ->groupBy('year', 'month', 'status')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return $orders->toArray();
    }

    public function getProductsReport(): array
    {
        $products = \App\Models\Product::withCount('orders')
            ->withCount('subscriptions')
            ->get();

        return $products->toArray();
    }

    public function getAccountsReport(): array
    {
        $accounts = Account::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return $accounts->toArray();
    }

    public function getSubscriptionsReport(): array
    {
        $subscriptions = Subscription::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return $subscriptions->toArray();
    }

    public function getDashboardMetrics(): array
    {
        return [
            'total_users' => \App\Models\User::count(),
            'total_products' => \App\Models\Product::count(),
            'total_accounts' => Account::count(),
            'available_accounts' => Account::where('status', 'available')->count(),
            'assigned_accounts' => Account::where('status', 'assigned')->count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'expiring_soon' => Subscription::where('status', 'expiring_soon')->count(),
            'expired_subscriptions' => Subscription::where('status', 'expired')->count(),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'approved_payments' => Payment::where('status', 'approved')->count(),
            'monthly_revenue' => Payment::where('status', 'approved')
                ->whereMonth('created_at', now(config('app.timezone'))->month)
                ->sum('amount'),
        ];
    }
}
