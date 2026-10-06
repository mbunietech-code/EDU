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

        $loanTotals = [
            'borrowed' => (float) FinanceCapitalEntry::where('is_loan', true)->sum('amount'),
            'repaid' => (float) FinanceLoanRepayment::sum('amount'),
        ];
        $loanTotals['outstanding'] = max(0, $loanTotals['borrowed'] - $loanTotals['repaid']);

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

    public function repaymentStore(Request $request, FinanceCapitalEntry $capitalEntry)
    {
        abort_unless($capitalEntry->is_loan, 404);

        $outstanding = $capitalEntry->outstandingAmount();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$outstanding],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'amount.max' => 'Amount cannot be more than the outstanding balance (TZS '.number_format($outstanding).').',
        ]);

        $repayment = $capitalEntry->repayments()->create($validated + ['created_by' => auth()->id()]);

        ActivityLog::log('finance_loan_repaid', 'FinanceCapitalEntry', $capitalEntry->id, [
            'repayment_id' => $repayment->id,
            'amount' => $validated['amount'],
            'outstanding' => $capitalEntry->outstandingAmount(),
        ]);

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

        $nextStaffNumber = $this->nextStaffNumber();

        return view('admin.finance.staff', compact('staff', 'departments', 'positions', 'employmentTypes', 'nextStaffNumber'));
    }

    public function staffStore(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'finance_department_id' => ['nullable', 'exists:finance_departments,id'],
            'finance_position_id' => ['nullable', 'exists:finance_positions,id'],
            'finance_employment_type_id' => ['nullable', 'exists:finance_employment_types,id'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'on_leave', 'suspended', 'resigned', 'terminated', 'retired', 'inactive'])],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_name_other' => ['nullable', 'required_if:bank_name,other', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:120'],
            'mobile_money' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (($validated['bank_name'] ?? null) === 'other') {
            $validated['bank_name'] = $validated['bank_name_other'];
        }
        unset($validated['bank_name_other']);

        $validated['staff_number'] = $this->nextStaffNumber();
        $validated['basic_salary'] = $validated['basic_salary'] ?? 0;
        $validated['created_by'] = auth()->id();

        $staff = FinanceStaff::create($validated);

        ActivityLog::log('finance_staff_created', 'FinanceStaff', $staff->id, [
            'staff_number' => $staff->staff_number,
            'name' => $staff->fullName(),
        ]);

        return back()->with('success', 'Staff member added.');
    }

    public function directorySettings(Request $request)
    {
        $type = $request->validate([
            'type' => ['required', Rule::in(['department', 'position', 'employment_type'])],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
            'finance_department_id' => ['nullable', 'exists:finance_departments,id'],
            'salary_grade' => ['nullable', 'string', 'max:80'],
            'seniority' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        if ($type['type'] === 'department') {
            FinanceDepartment::firstOrCreate(
                ['name' => $type['name']],
                ['code' => ($type['code'] ?? null) ?: null, 'status' => 'active']
            );
        } elseif ($type['type'] === 'position') {
            $position = FinancePosition::firstOrCreate(
                ['name' => $type['name']],
                [
                    'code' => ($type['code'] ?? null) ?: null,
                    'finance_department_id' => $type['finance_department_id'] ?? null,
                    'salary_grade' => $type['salary_grade'] ?? null,
                    'seniority' => $type['seniority'] ?? FinancePosition::guessSeniority($type['name']),
                    'status' => 'active',
                ]
            );

            // Re-adding an existing position with a seniority updates its rank.
            if (! $position->wasRecentlyCreated && isset($type['seniority'])) {
                $position->update(['seniority' => $type['seniority']]);
            }
        } else {
            FinanceEmploymentType::firstOrCreate(
                ['name' => $type['name']],
                ['code' => ($type['code'] ?? null) ?: null, 'status' => 'active']
            );
        }

        ActivityLog::log('finance_hr_setting_created', 'Finance', null, ['type' => $type['type'], 'name' => $type['name']]);

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

    public function payrollStore(Request $request, StatutoryPayrollService $statutory)
    {
        $validated = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'meal_allowance' => ['nullable', 'numeric', 'min:0'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'overtime_pay' => ['nullable', 'numeric', 'min:0'],
            'bonus_pay' => ['nullable', 'numeric', 'min:0'],
            'insurance_amount' => ['nullable', 'numeric', 'min:0'],
            'loan_deductions' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['period_month'])->startOfMonth();
        $existing = FinancePayrollPeriod::whereDate('period_month', $month->toDateString())->first();

        if ($existing && $existing->status !== 'draft') {
            return back()->with('error', "{$existing->name} payroll is already {$existing->status} and cannot be regenerated.");
        }

        $activeStaff = FinanceStaff::with(['department', 'position'])
            ->where('status', 'active')
            ->orderByRank()
            ->get();

        if ($activeStaff->isEmpty()) {
            return back()->with('error', 'Add active staff before preparing payroll.');
        }

        $staffCount = $activeStaff->count();
        $rates = $statutory->rates();
        $allowancePerStaff = $this->money($validated['transport_allowance'] ?? 0)
            + $this->money($validated['meal_allowance'] ?? 0)
            + $this->money($validated['housing_allowance'] ?? 0);
        $overtimePerStaff = $this->money($validated['overtime_pay'] ?? 0);
        $bonusPerStaff = $this->money($validated['bonus_pay'] ?? 0);
        $insurancePerStaff = $this->money($validated['insurance_amount'] ?? 0);
        $loanPerStaff = $this->money($validated['loan_deductions'] ?? 0);
        $otherDeductionPerStaff = $this->money($validated['other_deductions'] ?? 0);

        $rows = $activeStaff->map(function (FinanceStaff $staff) use ($statutory, $rates, $staffCount, $allowancePerStaff, $overtimePerStaff, $bonusPerStaff, $insurancePerStaff, $loanPerStaff, $otherDeductionPerStaff) {
            $basic = $this->money($staff->basic_salary);
            $gross = $basic + $allowancePerStaff + $overtimePerStaff + $bonusPerStaff;
            $s = $statutory->forStaff($basic, $gross, $staffCount, $rates);
            $deductions = $s['paye'] + $s['nssf_employee'] + $insurancePerStaff + $loanPerStaff + $otherDeductionPerStaff;

            return [
                'finance_staff_id' => $staff->id,
                'staff_number' => $staff->staff_number,
                'staff_name' => $staff->fullName(),
                'department_name' => $staff->department?->name,
                'position_name' => $staff->position?->name,
                'basic_pay' => $basic,
                'allowances' => $allowancePerStaff,
                'overtime_pay' => $overtimePerStaff,
                'bonus_pay' => $bonusPerStaff,
                'gross_pay' => $gross,
                'taxable_pay' => $s['taxable_pay'],
                'tax_amount' => $s['paye'],
                'pension_amount' => $s['nssf_employee'],
                'insurance_amount' => $insurancePerStaff,
                'loan_deduction' => $loanPerStaff,
                'other_deduction' => $otherDeductionPerStaff,
                'total_deductions' => $deductions,
                'net_pay' => max(0, $gross - $deductions),
                'nssf_employer' => $s['nssf_employer'],
                'sdl_amount' => $s['sdl'],
                'wcf_amount' => $s['wcf'],
                'leave_provision' => $s['leave_provision'],
                'severance_provision' => $s['severance_provision'],
                'gratuity_provision' => $s['gratuity_provision'],
                'employer_cost' => $s['employer_cost'],
                'payment_channel' => $staff->bank_account_number ? 'bank' : ($staff->mobile_money ? 'mobile_money' : null),
                'bank_name' => $staff->bank_name,
                'bank_account_number' => $staff->bank_account_number,
                'mobile_money' => $staff->mobile_money,
            ];
        });

        $totals = [
            'basic_pay' => $rows->sum('basic_pay'),
            'total_allowances' => $rows->sum('allowances'),
            'overtime_pay' => $rows->sum('overtime_pay'),
            'bonus_pay' => $rows->sum('bonus_pay'),
            'gross_pay' => $rows->sum('gross_pay'),
            'taxable_pay' => $rows->sum('taxable_pay'),
            'tax_amount' => $rows->sum('tax_amount'),
            'pension_amount' => $rows->sum('pension_amount'),
            'insurance_amount' => $rows->sum('insurance_amount'),
            'loan_deductions' => $rows->sum('loan_deduction'),
            'other_deductions' => $rows->sum('other_deduction'),
            'total_deductions' => $rows->sum('total_deductions'),
            'net_pay' => $rows->sum('net_pay'),
            'nssf_employer' => $rows->sum('nssf_employer'),
            'sdl_amount' => $rows->sum('sdl_amount'),
            'wcf_amount' => $rows->sum('wcf_amount'),
            'leave_provision' => $rows->sum('leave_provision'),
            'severance_provision' => $rows->sum('severance_provision'),
            'gratuity_provision' => $rows->sum('gratuity_provision'),
            'employer_cost' => $rows->sum('employer_cost'),
        ];
        $totals['total_provisions'] = $totals['leave_provision'] + $totals['severance_provision'] + $totals['gratuity_provision'];

        $payroll = DB::transaction(function () use ($existing, $month, $staffCount, $totals, $validated, $rows, $statutory) {
            $payroll = $existing ?? new FinancePayrollPeriod(['period_month' => $month->toDateString()]);
            $payroll->fill($totals + [
                'name' => $month->format('F Y'),
                'status' => 'draft',
                'staff_count' => $staffCount,
                'notes' => $validated['notes'] ?? null,
                'prepared_at' => now(),
                'created_by' => auth()->id(),
            ])->save();

            FinancePayrollItem::where('finance_payroll_period_id', $payroll->id)->delete();
            $payroll->items()->createMany($rows->all());

            $this->syncStatutoryReturns($payroll, $month, $statutory);

            return $payroll;
        });

        ActivityLog::log('finance_payroll_created', 'FinancePayrollPeriod', $payroll->id, [
            'period' => $payroll->name,
            'staff_count' => $payroll->staff_count,
            'net_pay' => $payroll->net_pay,
        ]);

        return back()->with('success', 'Payroll draft prepared with PAYE, NSSF, SDL, WCF and provisions. Statutory returns are listed under Returns.');
    }

    /**
     * Create or refresh the monthly PAYE / SDL / NSSF / WCF remittances for a
     * payroll period. Returns already marked paid are never changed.
     */
    protected function syncStatutoryReturns(FinancePayrollPeriod $payroll, Carbon $month, StatutoryPayrollService $statutory): void
    {
        $amounts = [
            'paye' => (float) $payroll->tax_amount,
            'sdl' => (float) $payroll->sdl_amount,
            'nssf' => (float) $payroll->pension_amount + (float) $payroll->nssf_employer,
            'wcf' => (float) $payroll->wcf_amount,
        ];

        foreach ($amounts as $type => $amount) {
            $return = $payroll->statutoryReturns()->where('type', $type)->first();

            if ($return?->isPaid()) {
                continue;
            }

            if ($amount <= 0) {
                $return?->delete();

                continue;
            }

            $payroll->statutoryReturns()->updateOrCreate(['type' => $type], [
                'authority' => config("finance.statutory.returns.{$type}.authority"),
                'amount' => round($amount, 2),
                'due_date' => $statutory->dueDate($type, $month)->toDateString(),
                'status' => 'pending',
            ]);
        }
    }

    public function statutorySettingsUpdate(Request $request, StatutoryPayrollService $statutory)
    {
        $rules = [];
        foreach (array_keys(config('finance.statutory.rates')) as $key) {
            $rules[$key] = $key === 'sdl_min_staff'
                ? ['required', 'integer', 'min:0', 'max:100000']
                : ['required', 'numeric', 'min:0', 'max:100'];
        }

        $validated = $request->validate($rules);
        $statutory->saveRates($validated);

        ActivityLog::log('finance_statutory_settings_updated', 'Finance', null, $validated);

        return back()->with('success', 'Statutory rates saved. They apply to the next payroll draft you prepare.');
    }

    public function returnsIndex(StatutoryPayrollService $statutory)
    {
        $returns = FinanceStatutoryReturn::with(['payrollPeriod', 'payer'])
            ->orderByRaw("CASE WHEN status = 'paid' THEN 1 ELSE 0 END")
            ->orderBy('due_date')
            ->paginate(30);

        $summary = [
            'overdue' => (float) FinanceStatutoryReturn::where('status', '!=', 'paid')->whereDate('due_date', '<', today())->sum('amount'),
            'pending' => (float) FinanceStatutoryReturn::where('status', '!=', 'paid')->whereDate('due_date', '>=', today())->sum('amount'),
            'paid_this_year' => (float) FinanceStatutoryReturn::where('status', 'paid')->whereYear('paid_at', now()->year)->sum('amount'),
            'provisions' => (float) FinancePayrollPeriod::sum('total_provisions'),
            'leave_provision' => (float) FinancePayrollPeriod::sum('leave_provision'),
            'severance_provision' => (float) FinancePayrollPeriod::sum('severance_provision'),
            'gratuity_provision' => (float) FinancePayrollPeriod::sum('gratuity_provision'),
        ];

        $rates = $statutory->rates();

        return view('admin.finance.returns', compact('returns', 'summary', 'rates'));
    }

    public function returnMarkPaid(Request $request, FinanceStatutoryReturn $statutoryReturn)
    {
        $validated = $request->validate([
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $statutoryReturn->update($validated + ['status' => 'paid', 'paid_by' => auth()->id()]);

        ActivityLog::log('finance_statutory_return_paid', 'FinanceStatutoryReturn', $statutoryReturn->id, [
            'type' => $statutoryReturn->type,
            'amount' => $statutoryReturn->amount,
            'reference' => $validated['reference'] ?? null,
        ]);

        return back()->with('success', $statutoryReturn->label().' marked as paid.');
    }

    public function returnMarkPending(FinanceStatutoryReturn $statutoryReturn)
    {
        $statutoryReturn->update(['status' => 'pending', 'paid_at' => null, 'reference' => null, 'paid_by' => null]);

        ActivityLog::log('finance_statutory_return_reopened', 'FinanceStatutoryReturn', $statutoryReturn->id, ['type' => $statutoryReturn->type]);

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

    protected function nextStaffNumber(): string
    {
        $last = FinanceStaff::withTrashed()
            ->where('staff_number', 'like', 'Mhub-%')
            ->pluck('staff_number')
            ->map(fn ($number) => (int) substr($number, 5))
            ->max() ?? 0;

        do {
            $number = 'Mhub-'.str_pad((string) ++$last, 3, '0', STR_PAD_LEFT);
        } while (FinanceStaff::withTrashed()->where('staff_number', $number)->exists());

        return $number;
    }
}
