<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService)
    {
    }

    public function revenue(Request $request)
    {
        $months = (int) $request->input('months', 12);
        $report = $this->reportService->getRevenueReport($months);

        return view('admin.reports.revenue', compact('report', 'months'));
    }

    public function orders(Request $request)
    {
        $months = (int) $request->input('months', 12);
        $report = $this->reportService->getOrdersReport($months);

        return view('admin.reports.orders', compact('report', 'months'));
    }

    public function products()
    {
        $report = $this->reportService->getProductsReport();

        return view('admin.reports.products', compact('report'));
    }

    public function accounts()
    {
        $report = $this->reportService->getAccountsReport();

        return view('admin.reports.accounts', compact('report'));
    }

    public function subscriptions()
    {
        $report = $this->reportService->getSubscriptionsReport();

        return view('admin.reports.subscriptions', compact('report'));
    }

    /**
     * Website views, app downloads and app opens — totals and unique devices,
     * per minute / hour / day / month / year.
     */
    public function analytics(Request $request, \App\Services\AnalyticsReport $analytics)
    {
        $w = $analytics->window(
            (string) $request->input('range', 'days'),
            $request->input('from'),
            $request->input('to'),
            $request->input('by'),
        );

        return view('admin.reports.analytics', [
            'w' => $w,
            'ranges' => \App\Services\AnalyticsReport::RANGES,
            'groups' => \App\Services\AnalyticsReport::GROUPS,
            'types' => \App\Services\AnalyticsReport::TYPES,
            'summary' => $analytics->summary(),
            'totals' => $analytics->totals($w),
            'series' => $analytics->series($w),
            'breakdowns' => $analytics->breakdowns($w),
            'topDevices' => $analytics->topDevices($w),
            'platforms' => \App\Support\AppDownloads::PLATFORMS,
        ]);
    }

    public function index()
    {
        $metrics = $this->reportService->getDashboardMetrics();

        return view('admin.reports.index', compact('metrics'));
    }
}