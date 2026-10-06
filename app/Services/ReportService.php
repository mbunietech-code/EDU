<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Account;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** "year, month" columns of created_at (MySQL in production, SQLite in tests). */
    private function yearMonth(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', created_at) AS INTEGER) as year, CAST(strftime('%m', created_at) AS INTEGER) as month"
            : 'YEAR(created_at) as year, MONTH(created_at) as month';
    }

    /**
     * Approved payments per product and per research tool since $months ago.
     *
     * @return array{products: array<int,float>, tools: array<int,float>}
     */
    public function getRevenueByItem(int $months = 12): array
    {
        $startDate = now(config('app.timezone'))->subMonths($months)->startOfMonth();
        $base = fn () => Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', 'approved')
            ->whereDate('payments.created_at', '>=', $startDate);

        return [
            'products' => $base()->whereNotNull('orders.product_id')->groupBy('orders.product_id')
                ->selectRaw('orders.product_id as id, SUM(payments.amount) as total')->pluck('total', 'id')
                ->map(fn ($v) => (float) $v)->all(),
            'tools' => $base()->whereNotNull('orders.tool_id')->groupBy('orders.tool_id')
                ->selectRaw('orders.tool_id as id, SUM(payments.amount) as total')->pluck('total', 'id')
                ->map(fn ($v) => (float) $v)->all(),
        ];
    }

    public function getRevenueReport(int $months = 12): array
    {
        $startDate = now(config('app.timezone'))->subMonths($months)->startOfMonth();

        $revenue = Payment::where('status', 'approved')
            ->whereDate('created_at', '>=', $startDate)
            ->selectRaw($this->yearMonth().', SUM(amount) as total')
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
            ->selectRaw($this->yearMonth().', COUNT(*) as total, status')
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
            'total_users' => \App\Models\User::query()->realUsers()->count(),
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
