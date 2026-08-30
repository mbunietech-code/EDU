<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $months = (int) $request->input('months', 6);
        $metrics = $this->reports->getDashboardMetrics();

        $monthName = fn ($row) => \Carbon\Carbon::create($row['year'], $row['month'], 1)->format('M Y');

        $revenue = collect($this->reports->getRevenueReport($months))
            ->map(fn ($row) => [
                'label' => $monthName($row),
                'value' => (float) $row['total'],
                'value_label' => 'TZS '.number_format((float) $row['total']),
            ])->values();

        $orders = collect($this->reports->getOrdersReport($months))
            ->groupBy(fn ($row) => $row['year'].'-'.str_pad((string) $row['month'], 2, '0', STR_PAD_LEFT))
            ->map(fn ($rows, $key) => [
                'label' => \Carbon\Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                'total' => collect($rows)->sum('total'),
                'by_status' => collect($rows)->mapWithKeys(fn ($r) => [$r['status'] => $r['total']])->all(),
            ])->values();

        return response()->json([
            'metrics' => [
                ['label' => 'Users', 'value' => $metrics['total_users']],
                ['label' => 'Products', 'value' => $metrics['total_products']],
                ['label' => 'Active subscriptions', 'value' => $metrics['active_subscriptions']],
                ['label' => 'Expiring soon', 'value' => $metrics['expiring_soon']],
                ['label' => 'Expired', 'value' => $metrics['expired_subscriptions']],
                ['label' => 'Pending payments', 'value' => $metrics['pending_payments']],
                ['label' => 'Available accounts', 'value' => $metrics['available_accounts']],
                ['label' => 'Assigned accounts', 'value' => $metrics['assigned_accounts']],
                ['label' => 'Revenue this month', 'value' => 'TZS '.number_format((float) $metrics['monthly_revenue'])],
            ],
            'revenue' => $revenue,
            'orders' => $orders,
            'products' => collect($this->reports->getProductsReport())->map(fn ($p) => [
                'name' => $p['name'] ?? '—',
                'orders' => $p['orders_count'] ?? 0,
                'subscriptions' => $p['subscriptions_count'] ?? 0,
            ])->sortByDesc('orders')->values(),
            'subscriptions' => collect($this->reports->getSubscriptionsReport())
                ->mapWithKeys(fn ($r) => [$r['status'] => $r['count']])->all(),
            'accounts' => collect($this->reports->getAccountsReport())
                ->mapWithKeys(fn ($r) => [$r['status'] => $r['count']])->all(),
        ]);
    }
}
