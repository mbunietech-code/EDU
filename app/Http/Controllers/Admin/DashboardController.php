<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CurrencyRateService;
use App\Services\ReportService;

class DashboardController extends Controller
{
    public function __construct(protected ReportService $reportService)
    {
    }

    public function index()
    {
        $metrics = $this->reportService->getDashboardMetrics();

        $recentOrders = Order::with(['user', 'product', 'tool'])
            ->latest()
            ->take(5)
            ->get();

        $recentPayments = Payment::with(['user', 'order.product'])
            ->latest()
            ->take(5)
            ->get();

        $revenueReport = $this->fillMissingMonths($this->reportService->getRevenueReport(6));

        $rates = app(CurrencyRateService::class)->rates();

        return view('admin.dashboard', compact('metrics', 'recentOrders', 'recentPayments', 'revenueReport', 'rates'));
    }

    protected function fillMissingMonths(array $revenue, int $months = 6): array
    {
        $byKey = collect($revenue)->keyBy(fn ($row) => $row['year'] . '-' . $row['month']);

        $filled = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now(config('app.timezone'))->subMonths($i);
            $key = $date->year . '-' . $date->month;

            $filled[] = $byKey->get($key, [
                'year' => $date->year,
                'month' => $date->month,
                'total' => 0,
            ]);
        }

        return $filled;
    }
}