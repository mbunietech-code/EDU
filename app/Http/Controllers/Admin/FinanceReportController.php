<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Finance\MonthlyReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Finance → Monthly report: profit and loss, profit per product, month-end checklist. */
class FinanceReportController extends Controller
{
    public function __construct(private MonthlyReportService $reports)
    {
    }

    public function index(Request $request)
    {
        $months = $this->reports->months();
        $month = $this->month($request, $months);
        $previous = $month->copy()->subMonthNoOverflow();

        return view('admin.finance.report', [
            'pnl' => $this->reports->pnl($month),
            'previous' => $this->reports->pnl($previous),
            'checklist' => $this->reports->checklist($month),
            'months' => $months,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $month = $this->month($request, $this->reports->months());
        $p = $this->reports->pnl($month);

        return response()->streamDownload(function () use ($p) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Mbunie EduHub – profit and loss', $p['month']]);
            fputcsv($out, []);
            fputcsv($out, ['Section', 'Line', 'TZS']);
            foreach ($p['income'] as $k => $v) {
                fputcsv($out, ['Income', $k, $v]);
            }
            fputcsv($out, ['Income', 'Total income', $p['income_total']]);
            foreach ($p['cogs'] as $k => $v) {
                fputcsv($out, ['Cost of sales', $k, $v]);
            }
            fputcsv($out, ['Cost of sales', 'Total cost of sales', $p['cogs_total']]);
            fputcsv($out, ['Gross profit', '', $p['gross']]);
            foreach ($p['opex'] as $k => $v) {
                fputcsv($out, ['Operating expenses', $k, $v]);
            }
            fputcsv($out, ['Operating expenses', 'Total operating expenses', $p['opex_total']]);
            fputcsv($out, ['Net profit', '', $p['net']]);
            fputcsv($out, []);
            fputcsv($out, ['Cash outside profit', 'Owner capital in', $p['cash']['capital_in']]);
            fputcsv($out, ['Cash outside profit', 'Loans received', $p['cash']['loans_in']]);
            fputcsv($out, ['Cash outside profit', 'Loans repaid', $p['cash']['repaid']]);
            fclose($out);
        }, "finance-pnl-{$p['month']}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function month(Request $request, array $months): Carbon
    {
        $m = (string) $request->query('month', $months[0] ?? now()->format('Y-m'));

        return Carbon::createFromFormat('!Y-m', preg_match('/^\d{4}-\d{2}$/', $m) ? $m : now()->format('Y-m'));
    }
}
