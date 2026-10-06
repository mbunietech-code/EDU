<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceDepartment;
use App\Models\FinanceEmploymentType;
use App\Models\FinanceExpense;
use App\Models\FinanceLoanRepayment;
use App\Models\FinancePayrollItem;
use App\Models\FinancePayrollPeriod;
use App\Models\FinancePosition;
use App\Models\FinanceStaff;
use App\Models\FinanceStaffContract;
use App\Models\FinanceStatutoryReturn;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tool;
use App\Services\DeletionService;
use App\Services\Finance\FinanceException;
use App\Services\Finance\LoanService;
use App\Services\Finance\PayrollService;
use App\Services\Finance\StaffService;
use App\Services\FinanceOverviewService;
use App\Services\StatutoryPayrollService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    /**
     * Validation rules for an uploaded expense receipt (image or PDF, max 5 MB).
     */
    private const RECEIPT_RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];

    public function pin()
    {
        if (session('finance_unlocked')) {
            return redirect()->route('admin.finance.dashboard');
        }

        return view('admin.finance.pin');
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits:6'],
        ]);

        $key = 'finance-pin:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['pin' => "Too many attempts. Try again in {$seconds} seconds."]);
        }

        if ($validated['pin'] !== Setting::get('finance_pin')) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['pin' => 'Incorrect PIN.']);
        }

        RateLimiter::clear($key);

        $request->session()->put('finance_unlocked', true);

        ActivityLog::log('finance_unlocked', 'Finance', null, []);

        return redirect()->route('admin.finance.dashboard');
    }

    public function lock(Request $request)
    {
        $request->session()->forget('finance_unlocked');

        return redirect()->route('admin.finance.pin');
    }

    public function dashboard(FinanceOverviewService $overview)
    {
        $rows = $overview->rows();
        $totals = $overview->totals($rows);

        return view('admin.finance.dashboard', compact('rows', 'totals'));
    }

    public function capitalIndex()
    {
        $entries = FinanceCapitalEntry::with(['product', 'tool', 'creator'])
            ->withSum('repayments', 'amount')
            ->latest()
            ->paginate(20);
        $products = Product::orderBy('name')->get(['id', 'name']);
        $tools = Tool::orderBy('name')->get(['id', 'name']);

        $loanTotals = app(LoanService::class)->totals();

        return view('admin.finance.capital', compact('entries', 'products', 'tools', 'loanTotals'));
    }

    public function capitalStore(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'source' => ['required', 'string', 'max:255'],
            'is_loan' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['created_by'] = auth()->id();
        $validated['label'] = $this->resolveLabel($validated);

        FinanceCapitalEntry::create($validated);

        ActivityLog::log('finance_capital_added', 'FinanceCapitalEntry', null, ['label' => $validated['label'], 'amount' => $validated['amount']]);

        return back()->with('success', 'Capital entry recorded.');
    }

    public function capitalEdit(FinanceCapitalEntry $capitalEntry)
    {
        $products = Product::orderBy('name')->get(['id', 'name']);
        $tools = Tool::orderBy('name')->get(['id', 'name']);

        return view('admin.finance.capital-edit', compact('capitalEntry', 'products', 'tools'));
    }

    public function capitalUpdate(Request $request, FinanceCapitalEntry $capitalEntry)
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'source' => ['required', 'string', 'max:255'],
            'is_loan' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['label'] = $this->resolveLabel($validated);

        $repaid = $capitalEntry->repaidAmount();
        if ($repaid > 0 && ! ($validated['is_loan'] ?? false)) {
            return back()->withInput()->withErrors(['is_loan' => 'This loan already has repayments. Remove them before unmarking it as a loan.']);
        }
        if ($repaid > (float) $validated['amount']) {
            return back()->withInput()->withErrors(['amount' => 'Amount cannot be less than what has already been repaid (TZS '.number_format($repaid).').']);
        }

        $capitalEntry->update($validated);

        ActivityLog::log('finance_capital_updated', 'FinanceCapitalEntry', $capitalEntry->id, ['label' => $validated['label'], 'amount' => $validated['amount']]);

        return redirect()->route('admin.finance.capital.index')->with('success', 'Capital entry updated.');
    }

    public function capitalDestroy(Request $request, FinanceCapitalEntry $capitalEntry, DeletionService $deletionService)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $deletionService->delete($capitalEntry, $validated['reason']);

        return back()->with('success', 'Capital entry removed.');
    }

    public function repaymentIndex(FinanceCapitalEntry $capitalEntry)
    {
        abort_unless($capitalEntry->is_loan, 404);

        $capitalEntry->load(['repayments.creator', 'creator']);

        return view('admin.finance.repayments', compact('capitalEntry'));
    }

    public function repaymentStore(Request $request, FinanceCapitalEntry $capitalEntry, LoanService $loans)
    {
        abort_unless($capitalEntry->is_loan, 404);

        [$rules, $messages] = $loans->repaymentRules($capitalEntry);
        $loans->repay($capitalEntry, $request->validate($rules, $messages), auth()->id());

        return back()->with('success', $capitalEntry->isFullyRepaid() ? 'Repayment recorded. The loan is fully repaid.' : 'Repayment recorded.');
    }

    public function repaymentDestroy(Request $request, FinanceCapitalEntry $capitalEntry, FinanceLoanRepayment $repayment, DeletionService $deletionService)
    {
        abort_unless((int) $repayment->finance_capital_entry_id === (int) $capitalEntry->id, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $deletionService->delete($repayment, $validated['reason']);

        return back()->with('success', 'Repayment removed.');
    }

    public function expenseIndex()
    {
        $expenses = FinanceExpense::with(['product', 'tool', 'creator'])->latest('spent_at')->paginate(20);
        $products = Product::orderBy('name')->get(['id', 'name']);
        $tools = Tool::orderBy('name')->get(['id', 'name']);

        $receiptsSupported = $this->receiptsSupported();

        return view('admin.finance.expenses', compact('expenses', 'products', 'tools', 'receiptsSupported'));
    }

    public function expenseStore(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['nullable', Rule::in([
                'Operating Expenses (OPEX)',
                'Cost of Goods Sold (COGS)',
                'Financial Expenses',
                'Depreciation & Amortization',
                'Miscellaneous',
            ])],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt' => self::RECEIPT_RULES,
            'spent_at' => ['required', 'date'],
        ]);

        unset($validated['receipt']);
        $validated['created_by'] = auth()->id();
        $validated['label'] = $this->resolveLabel($validated);

        if ($this->receiptsSupported() && $request->hasFile('receipt')) {
            $validated['receipt_path'] = $request->file('receipt')->store('finance-receipts', 'private');
        }

        FinanceExpense::create($validated);

        ActivityLog::log('finance_expense_added', 'FinanceExpense', null, ['label' => $validated['label'], 'amount' => $validated['amount']]);

        return back()->with('success', 'Expense recorded.');
    }

    public function expenseEdit(FinanceExpense $expense)
    {
        $products = Product::orderBy('name')->get(['id', 'name']);
        $tools = Tool::orderBy('name')->get(['id', 'name']);

        $receiptsSupported = $this->receiptsSupported();

        return view('admin.finance.expense-edit', compact('expense', 'products', 'tools', 'receiptsSupported'));
    }

    public function expenseUpdate(Request $request, FinanceExpense $expense)
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['nullable', Rule::in([
                'Operating Expenses (OPEX)',
                'Cost of Goods Sold (COGS)',
                'Financial Expenses',
                'Depreciation & Amortization',
                'Miscellaneous',
            ])],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt' => self::RECEIPT_RULES,
            'remove_receipt' => ['nullable', 'boolean'],
            'spent_at' => ['required', 'date'],
        ]);

        $removeReceipt = (bool) ($validated['remove_receipt'] ?? false);
        unset($validated['receipt'], $validated['remove_receipt']);

        $validated['label'] = $this->resolveLabel($validated);

        if ($this->receiptsSupported()) {
            if ($request->hasFile('receipt')) {
                $this->deleteReceiptFile($expense->receipt_path);
                $validated['receipt_path'] = $request->file('receipt')->store('finance-receipts', 'private');
            } elseif ($removeReceipt) {
                $this->deleteReceiptFile($expense->receipt_path);
                $validated['receipt_path'] = null;
            }
        }

        $expense->update($validated);

        ActivityLog::log('finance_expense_updated', 'FinanceExpense', $expense->id, ['label' => $validated['label'], 'amount' => $validated['amount']]);

        return redirect()->route('admin.finance.expenses.index')->with('success', 'Expense updated.');
    }

    public function expenseReceipt(Request $request, FinanceExpense $expense): StreamedResponse
    {
        abort_unless($expense->hasReceipt(), 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($expense->receipt_path), 404);

        $extension = pathinfo($expense->receipt_path, PATHINFO_EXTENSION) ?: 'file';
        $name = 'receipt-'.\Illuminate\Support\Str::slug($expense->label ?: 'expense').'-'.$expense->id.'.'.$extension;

        // ?download=1 forces a file download; otherwise it opens inline.
        return $request->boolean('download')
            ? $disk->download($expense->receipt_path, $name)
            : $disk->response($expense->receipt_path, $name);
    }

    public function expenseDestroy(Request $request, FinanceExpense $expense, DeletionService $deletionService)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $receiptPath = $expense->receipt_path;

        $deletionService->delete($expense, $validated['reason']);

        $this->deleteReceiptFile($receiptPath);

        return back()->with('success', 'Expense removed.');
    }

    public function staffDashboard()
    {
        $today = now()->toDateString();
        $soon = now()->addDays(30)->toDateString();

        $metrics = [
            'total_staff' => FinanceStaff::count(),
            'active_staff' => FinanceStaff::where('status', 'active')->count(),
            'departments' => FinanceDepartment::count(),
            'active_contracts' => FinanceStaffContract::where('status', 'active')->count(),
            'expiring_contracts' => FinanceStaffContract::where('status', 'active')->whereBetween('end_date', [$today, $soon])->count(),
            'expired_contracts' => FinanceStaffContract::whereNotNull('end_date')->where('end_date', '<', $today)->whereNotIn('status', ['renewed', 'terminated', 'cancelled'])->count(),
            'current_payroll_net' => (float) FinancePayrollPeriod::latest('period_month')->value('net_pay'),
        ];

        $recentStaff = FinanceStaff::with(['department', 'position'])->orderByRank()->limit(8)->get();
        $expiringContracts = FinanceStaffContract::with(['staff', 'department', 'position'])
            ->where('status', 'active')
            ->whereBetween('end_date', [$today, $soon])
            ->orderBy('end_date')
            ->limit(8)
            ->get();
        $payrolls = FinancePayrollPeriod::latest('period_month')->limit(5)->get();

        return view('admin.finance.staff-dashboard', compact('metrics', 'recentStaff', 'expiringContracts', 'payrolls'));
    }

    public function staffIndex(Request $request)
    {
        $query = FinanceStaff::with(['department', 'position', 'employmentType'])->orderByRank();

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('staff_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $staff = $query->paginate(20)->withQueryString();
        $departments = FinanceDepartment::orderBy('name')->get();
        $positions = FinancePosition::orderBy('seniority')->orderBy('name')->get();
        $employmentTypes = FinanceEmploymentType::orderBy('name')->get();

        $nextStaffNumber = app(StaffService::class)->nextStaffNumber();

        return view('admin.finance.staff', compact('staff', 'departments', 'positions', 'employmentTypes', 'nextStaffNumber'));
    }

    public function staffStore(Request $request, StaffService $staffService)
    {
        $staffService->create($request->validate(StaffService::rules()), auth()->id());

        return back()->with('success', 'Staff member added.');
    }

    public function directorySettings(Request $request, StaffService $staffService)
    {
        $staffService->createSetting($request->validate(StaffService::settingRules()));

        return back()->with('success', 'HR setting saved.');
    }

    public function contractsIndex()
    {
        $contracts = FinanceStaffContract::with(['staff', 'department', 'position'])->latest('start_date')->paginate(20);
        $staff = FinanceStaff::orderBy('first_name')->orderBy('last_name')->get();
        $departments = FinanceDepartment::orderBy('name')->get();
        $positions = FinancePosition::orderBy('seniority')->orderBy('name')->get();
        $employmentTypes = FinanceEmploymentType::orderBy('name')->get();

        return view('admin.finance.contracts', compact('contracts', 'staff', 'departments', 'positions', 'employmentTypes'));
    }

    public function contractsStore(Request $request)
    {
        $validated = $request->validate([
            'finance_staff_id' => ['required', 'exists:finance_staff,id'],
            'contract_number' => ['nullable', 'string', 'max:80', 'unique:finance_staff_contracts,contract_number'],
            'contract_type' => ['nullable', 'string', 'max:120'],
            'finance_department_id' => ['nullable', 'exists:finance_departments,id'],
            'finance_position_id' => ['nullable', 'exists:finance_positions,id'],
            'finance_employment_type_id' => ['nullable', 'exists:finance_employment_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'salary_grade' => ['nullable', 'string', 'max:80'],
            'leave_entitlement_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['required', Rule::in(['draft', 'active', 'expiring', 'expired', 'renewed', 'terminated', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $staff = FinanceStaff::findOrFail($validated['finance_staff_id']);
        $validated['contract_number'] = $validated['contract_number'] ?: 'CNT-'.now()->format('Ymd').'-'.str_pad((string) (FinanceStaffContract::count() + 1), 4, '0', STR_PAD_LEFT);
        $validated['finance_department_id'] ??= $staff->finance_department_id;
        $validated['finance_position_id'] ??= $staff->finance_position_id;
        $validated['finance_employment_type_id'] ??= $staff->finance_employment_type_id;
        $validated['created_by'] = auth()->id();

        $contract = FinanceStaffContract::create($validated);

        if ($contract->status === 'active') {
            FinanceStaffContract::where('finance_staff_id', $staff->id)
                ->where('id', '!=', $contract->id)
                ->where('status', 'active')
                ->update(['status' => 'renewed']);

            $staff->update([
                'basic_salary' => $contract->basic_salary,
                'finance_department_id' => $contract->finance_department_id,
                'finance_position_id' => $contract->finance_position_id,
                'finance_employment_type_id' => $contract->finance_employment_type_id,
                'status' => 'active',
            ]);
        }

        ActivityLog::log('finance_contract_created', 'FinanceStaffContract', $contract->id, [
            'contract_number' => $contract->contract_number,
            'staff_id' => $staff->staff_number,
        ]);

        return back()->with('success', 'Contract saved.');
    }

    public function payrollIndex(StatutoryPayrollService $statutory)
    {
        $payrolls = FinancePayrollPeriod::withCount('items')->latest('period_month')->paginate(20);
        $activeStaffCount = FinanceStaff::where('status', 'active')->count();
        $activeSalaryTotal = (float) FinanceStaff::where('status', 'active')->sum('basic_salary');
        $activeStaff = FinanceStaff::with(['department', 'position'])
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(12)
            ->get();
        $latestPayroll = FinancePayrollPeriod::with(['items' => fn ($q) => $q->orderByDesc('gross_pay')->limit(10), 'statutoryReturns'])
            ->latest('period_month')
            ->first();
        $payrollTotals = [
            'drafts' => FinancePayrollPeriod::where('status', 'draft')->count(),
            'approved' => FinancePayrollPeriod::where('status', 'approved')->count(),
            'paid' => FinancePayrollPeriod::where('status', 'paid')->count(),
            'latest_net' => (float) ($latestPayroll?->net_pay ?? 0),
            'latest_deductions' => (float) ($latestPayroll?->total_deductions ?? 0),
            'latest_employer_cost' => (float) ($latestPayroll?->employer_cost ?? 0),
        ];

        $rates = $statutory->rates();

        return view('admin.finance.payroll', compact('payrolls', 'activeStaffCount', 'activeSalaryTotal', 'activeStaff', 'latestPayroll', 'payrollTotals', 'rates'));
    }

    public function payrollStore(Request $request, PayrollService $payrolls)
    {
        try {
            $payrolls->prepare($request->validate(PayrollService::RULES), auth()->id());
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payroll draft prepared with PAYE, NSSF, SDL, WCF and provisions. Statutory returns are listed under Returns.');
    }

    public function statutorySettingsUpdate(Request $request, PayrollService $payrolls)
    {
        $payrolls->saveRates($request->validate($payrolls->rateRules()));

        return back()->with('success', 'Statutory rates saved. They apply to the next payroll draft you prepare.');
    }

    public function returnsIndex(StatutoryPayrollService $statutory, PayrollService $payrolls)
    {
        $returns = FinanceStatutoryReturn::with(['payrollPeriod', 'payer'])
            ->orderByRaw("CASE WHEN status = 'paid' THEN 1 ELSE 0 END")
            ->orderBy('due_date')
            ->paginate(30);

        $summary = $payrolls->returnsSummary();
        $rates = $statutory->rates();

        return view('admin.finance.returns', compact('returns', 'summary', 'rates'));
    }

    public function returnMarkPaid(Request $request, FinanceStatutoryReturn $statutoryReturn, PayrollService $payrolls)
    {
        $payrolls->markReturnPaid($statutoryReturn, $request->validate(PayrollService::RETURN_PAID_RULES), auth()->id());

        return back()->with('success', $statutoryReturn->label().' marked as paid.');
    }

    public function returnMarkPending(FinanceStatutoryReturn $statutoryReturn, PayrollService $payrolls)
    {
        $payrolls->markReturnPending($statutoryReturn);

        return back()->with('success', $statutoryReturn->label().' moved back to pending.');
    }

    protected function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    protected function deleteReceiptFile(?string $path): void
    {
        if (filled($path) && Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }
    }

    /**
     * Whether the receipt_path column exists yet (the schema change may not
     * have been applied on this database). Cached per request.
     */
    protected function receiptsSupported(): bool
    {
        static $supported = null;

        if ($supported === null) {
            $supported = \Illuminate\Support\Facades\Schema::hasColumn('finance_expenses', 'receipt_path');
        }

        return $supported;
    }

    protected function resolveLabel(array $validated): string
    {
        if (! empty($validated['product_id'])) {
            return Product::find($validated['product_id'])->name;
        }

        if (! empty($validated['tool_id'])) {
            return Tool::find($validated['tool_id'])->name;
        }

        return $validated['label'];
    }

}
