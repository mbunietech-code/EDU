<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ReportService;

class DashboardController extends Controller
{
    public function __construct(protected ReportService $reportService)
    {
    }

    public function index()
    {
        $metrics = $this->reportService->getDashboardMetrics();

        $recentOrders = Order::with(['user', 'product'])
            ->latest()
            ->take(10)
            ->get();

        $recentPayments = Payment::with(['user', 'order.product'])
            ->latest()
            ->take(10)
            ->get();

        $revenueReport = $this->reportService->getRevenueReport(6);

        return view('admin.dashboard', compact('metrics', 'recentOrders', 'recentPayments', 'revenueReport'));
    }
}