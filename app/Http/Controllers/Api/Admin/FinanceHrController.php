<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\FinanceAccess;
use App\Http\Controllers\Controller;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceDepartment;
use App\Models\FinanceEmploymentType;
use App\Models\FinanceLoanRepayment;
use App\Models\FinancePayrollPeriod;
use App\Models\FinancePosition;
use App\Models\FinanceStaff;
use App\Models\FinanceStatutoryReturn;
use App\Services\DeletionService;
use App\Services\Finance\FinanceException;
use App\Services\Finance\LoanService;
use App\Services\Finance\PayrollService;
use App\Services\Finance\StaffService;
use App\Services\StatutoryPayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Finance HR for the app: staff, payroll with statutory deductions,
 * statutory returns and loan repayments. Same services as the web pages.
 */
class FinanceHrController extends Controller
{
    use FinanceAccess;

    // --- Staff ---------------------------------------------------------------

    public function staff(Request $request, StaffService $staffService): JsonResponse
    {
        $this->finance($request);

        $search = trim((string) $request->query('q'));
        $staff = FinanceStaff::with(['department', 'position', 'employmentType'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('staff_number', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByRank()
            ->limit(200)
            ->get();

        return response()->json(['data' => [
            'staff' => $staff->map(fn (FinanceStaff $s) => [
                'id' => $s->id,
                'staff_number' => $s->staff_number,
                'name' => $s->fullName(),
                'department' => $s->department?->name,
                'position' => $s->position?->name,
                'employment_type' => $s->employmentType?->name,
                'status' => $s->status,
                'basic_salary' => (float) $s->basic_salary,
                'phone' => $s->phone,
                'email' => $s->email,
                'bank_name' => $s->bank_name,
                'bank_account_number' => $s->bank_account_number,
                'mobile_money' => $s->mobile_money,
                'hire_date' => $s->hire_date?->toDateString(),
            ])->values(),
            'metrics' => [
                'total' => FinanceStaff::count(),
                'active' => FinanceStaff::where('status', 'active')->count(),
                'salary_base' => (float) FinanceStaff::where('status', 'active')->sum('basic_salary'),
            ],
            'next_staff_number' => $staffService->nextStaffNumber(),
            'options' => [
                'departments' => FinanceDepartment::orderBy('name')->get(['id', 'name']),
                'positions' => FinancePosition::orderBy('seniority')->orderBy('name')->get(['id', 'name', 'seniority']),
                'employment_types' => FinanceEmploymentType::orderBy('name')->get(['id', 'name']),
                'banks' => config('finance.banks'),
                'statuses' => StaffService::STATUSES,
            ],
        ]]);
    }

    public function storeStaff(Request $request, StaffService $staffService): JsonResponse
    {
        $this->finance($request);

        $staff = $staffService->create($request->validate(StaffService::rules()), $request->user()->id);

        return response()->json(['data' => ['id' => $staff->id, 'staff_number' => $staff->staff_number], 'message' => 'Staff member added as '.$staff->staff_number.'.'], 201);
    }

    public function storeSetting(Request $request, StaffService $staffService): JsonResponse
    {
        $this->finance($request);

        $staffService->createSetting($request->validate(StaffService::settingRules()));

        return response()->json(['message' => 'HR setting saved.'], 201);
    }

    // --- Payroll -------------------------------------------------------------

    public function payroll(Request $request, StatutoryPayrollService $statutory): JsonResponse
    {
        $this->finance($request);

        $latest = FinancePayrollPeriod::with(['items' => fn ($q) => $q->orderByDesc('gross_pay'), 'statutoryReturns'])
            ->latest('period_month')->first();

        return response()->json(['data' => [
            'periods' => FinancePayrollPeriod::latest('period_month')->limit(24)->get()->map(fn (FinancePayrollPeriod $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'staff_count' => (int) $p->staff_count,
                'gross_pay' => (float) $p->gross_pay,
                'total_deductions' => (float) $p->total_deductions,
                'net_pay' => (float) $p->net_pay,
                'employer_cost' => (float) $p->employer_cost,
            ])->values(),
            'latest' => $latest ? [
                'id' => $latest->id,
                'name' => $latest->name,
                'status' => $latest->status,
                'gross_pay' => (float) $latest->gross_pay,
                'paye' => (float) $latest->tax_amount,
                'nssf_staff' => (float) $latest->pension_amount,
                'nssf_employer' => (float) $latest->nssf_employer,
                'sdl' => (float) $latest->sdl_amount,
                'wcf' => (float) $latest->wcf_amount,
                'provisions' => (float) $latest->total_provisions,
                'total_deductions' => (float) $latest->total_deductions,
                'net_pay' => (float) $latest->net_pay,
                'employer_cost' => (float) $latest->employer_cost,
                'returns_pending' => $latest->statutoryReturns->where('status', '!=', 'paid')->count(),
                'items' => $latest->items->map(fn ($i) => [
                    'staff_name' => $i->staff_name,
                    'staff_number' => $i->staff_number,
                    'position' => $i->position_name,
                    'gross_pay' => (float) $i->gross_pay,
                    'paye' => (float) $i->tax_amount,
                    'nssf' => (float) $i->pension_amount,
                    'total_deductions' => (float) $i->total_deductions,
                    'net_pay' => (float) $i->net_pay,
                    'payment_channel' => $i->payment_channel,
                ])->values(),
            ] : null,
            'active_staff' => FinanceStaff::where('status', 'active')->count(),
            'rates' => $statutory->rates(),
        ]]);
    }

    public function preparePayroll(Request $request, PayrollService $payrolls): JsonResponse
    {
        $this->finance($request);

        try {
            $payroll = $payrolls->prepare($request->validate(PayrollService::RULES), $request->user()->id);
        } catch (FinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => ['id' => $payroll->id, 'name' => $payroll->name, 'net_pay' => (float) $payroll->net_pay],
            'message' => 'Payroll draft prepared with PAYE, NSSF, SDL, WCF and provisions.',
        ], 201);
    }

    public function updateRates(Request $request, PayrollService $payrolls): JsonResponse
    {
        $this->finance($request);

        $payrolls->saveRates($request->validate($payrolls->rateRules()));

        return response()->json(['message' => 'Statutory rates saved. They apply to the next payroll draft.']);
    }

    // --- Statutory returns ---------------------------------------------------

    public function returns(Request $request, PayrollService $payrolls): JsonResponse
    {
        $this->finance($request);

        $returns = FinanceStatutoryReturn::with('payrollPeriod:id,name')
            ->orderByRaw("CASE WHEN status = 'paid' THEN 1 ELSE 0 END")
            ->orderBy('due_date')
            ->limit(100)
            ->get();

        return response()->json(['data' => [
            'summary' => $payrolls->returnsSummary(),
            'returns' => $returns->map(fn (FinanceStatutoryReturn $r) => $this->returnRow($r))->values(),
        ]]);
    }

    public function markReturnPaid(Request $request, FinanceStatutoryReturn $statutoryReturn, PayrollService $payrolls): JsonResponse
    {
        $this->finance($request);

        $payrolls->markReturnPaid($statutoryReturn, $request->validate(PayrollService::RETURN_PAID_RULES), $request->user()->id);

        return response()->json(['data' => $this->returnRow($statutoryReturn->fresh('payrollPeriod')), 'message' => $statutoryReturn->label().' marked as paid.']);
    }

    public function markReturnPending(Request $request, FinanceStatutoryReturn $statutoryReturn, PayrollService $payrolls): JsonResponse
    {
        $this->finance($request);

        $payrolls->markReturnPending($statutoryReturn);

        return response()->json(['data' => $this->returnRow($statutoryReturn->fresh('payrollPeriod')), 'message' => $statutoryReturn->label().' moved back to pending.']);
    }

    // --- Loans ---------------------------------------------------------------

    public function loans(Request $request, LoanService $loans): JsonResponse
    {
        $this->finance($request);

        $entries = FinanceCapitalEntry::where('is_loan', true)->withSum('repayments', 'amount')->latest()->get();

        return response()->json(['data' => [
            'totals' => $loans->totals(),
            'loans' => $entries->map(fn (FinanceCapitalEntry $e) => $this->loanRow($e))->values(),
        ]]);
    }

    public function loan(Request $request, FinanceCapitalEntry $capitalEntry): JsonResponse
    {
        $this->finance($request);
        abort_unless($capitalEntry->is_loan, 404);

        $capitalEntry->load('repayments.creator:id,name');

        return response()->json(['data' => $this->loanRow($capitalEntry) + [
            'repayments' => $capitalEntry->repayments->map(fn (FinanceLoanRepayment $r) => [
                'id' => $r->id,
                'amount' => (float) $r->amount,
                'paid_at' => $r->paid_at?->toDateString(),
                'method' => $r->method,
                'reference' => $r->reference,
                'notes' => $r->notes,
                'recorded_by' => $r->creator?->name,
            ])->values(),
        ]]);
    }

    public function repay(Request $request, FinanceCapitalEntry $capitalEntry, LoanService $loans): JsonResponse
    {
        $this->finance($request);
        abort_unless($capitalEntry->is_loan, 404);

        [$rules, $messages] = $loans->repaymentRules($capitalEntry);
        $loans->repay($capitalEntry, $request->validate($rules, $messages), $request->user()->id);

        return response()->json([
            'data' => $this->loanRow($capitalEntry->fresh()),
            'message' => $capitalEntry->isFullyRepaid() ? 'Repayment recorded. The loan is fully repaid.' : 'Repayment recorded.',
        ], 201);
    }

    public function destroyRepayment(Request $request, FinanceCapitalEntry $capitalEntry, FinanceLoanRepayment $repayment, DeletionService $deletion): JsonResponse
    {
        $this->finance($request);
        abort_unless((int) $repayment->finance_capital_entry_id === (int) $capitalEntry->id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $deletion->delete($repayment, $data['reason']);

        return response()->json(['message' => 'Repayment removed.']);
    }

    // --- Rows ----------------------------------------------------------------

    protected function returnRow(FinanceStatutoryReturn $r): array
    {
        return [
            'id' => $r->id,
            'type' => $r->type,
            'label' => $r->label(),
            'authority' => $r->authority,
            'period' => $r->payrollPeriod?->name,
            'amount' => (float) $r->amount,
            'due_date' => $r->due_date?->toDateString(),
            'status' => $r->isPaid() ? 'paid' : ($r->isOverdue() ? 'overdue' : 'pending'),
            'paid_at' => $r->paid_at?->toDateString(),
            'reference' => $r->reference,
        ];
    }

    protected function loanRow(FinanceCapitalEntry $e): array
    {
        return [
            'id' => $e->id,
            'label' => $e->label,
            'source' => $e->source,
            'amount' => (float) $e->amount,
            'repaid' => $e->repaidAmount(),
            'outstanding' => $e->outstandingAmount(),
            'fully_repaid' => $e->isFullyRepaid(),
            'recorded_at' => $e->created_at?->toDateString(),
            'notes' => $e->notes,
        ];
    }
}
