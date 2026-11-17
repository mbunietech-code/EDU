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
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected ReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        // Every admin opens the dashboard, but money and customer lists only
        // reach admins who may open those pages (same permissions as the menu).
        $can = self::sections($request->user());

        $metrics = $this->reportService->getDashboardMetrics();

        $recentOrders = $can['orders']
            ? Order::with(['user', 'product', 'tool', 'subscription'])->latest()->take(5)->get()
            : collect();

        $recentPayments = $can['payments']
            ? Payment::with(['user', 'order.product'])->latest()->take(5)->get()
            : collect();

        $revenueReport = $can['revenue']
            ? $this->fillMissingMonths($this->reportService->getRevenueReport(6))
            : [];

        $rates = app(CurrencyRateService::class)->rates();

        return view('admin.dashboard', compact('metrics', 'recentOrders', 'recentPayments', 'revenueReport', 'rates', 'can'));
    }

    /**
     * Which dashboard parts this admin may see (also used by the MHub app dashboard).
     *
     * @return array{revenue:bool,orders:bool,payments:bool}
     */
    public static function sections(User $user): array
    {
        return [
            'revenue' => $user->can('reports.view'),
            'orders' => $user->can('orders.view'),
            'payments' => $user->can('payments.view'),
        ];
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
