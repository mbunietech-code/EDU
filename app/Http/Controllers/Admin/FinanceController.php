<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceDepartment;
use App\Models\FinanceEmploymentType;
use App\Models\FinanceExpense;
use App\Models\FinancePayrollPeriod;
use App\Models\FinancePosition;
use App\Models\FinanceStaff;
use App\Models\FinanceStaffContract;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tool;
use App\Services\DeletionService;
use App\Services\FinanceOverviewService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
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
        $entries = FinanceCapitalEntry::with(['product', 'tool', 'creator'])->latest()->paginate(20);
        $products = Product::orderBy('name')->get(['id', 'name']);
        $tools = Tool::orderBy('name')->get(['id', 'name']);

        return view('admin.finance.capital', compact('entries', 'products', 'tools'));
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

        $recentStaff = FinanceStaff::with(['department', 'position'])->latest()->limit(6)->get();
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
        $query = FinanceStaff::with(['department', 'position', 'employmentType'])->latest();

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
        $positions = FinancePosition::orderBy('name')->get();
        $employmentTypes = FinanceEmploymentType::orderBy('name')->get();

        return view('admin.finance.staff', compact('staff', 'departments', 'positions', 'employmentTypes'));
    }

    public function staffStore(Request $request)
    {
        $validated = $request->validate([
            'staff_number' => ['nullable', 'string', 'max:40', 'unique:finance_staff,staff_number'],
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
            'bank_account_number' => ['nullable', 'string', 'max:120'],
            'mobile_money' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['staff_number'] = $validated['staff_number'] ?: $this->nextStaffNumber();
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
        ]);

        if ($type['type'] === 'department') {
            FinanceDepartment::firstOrCreate(
                ['name' => $type['name']],
                ['code' => $type['code'] ?: null, 'status' => 'active']
            );
        } elseif ($type['type'] === 'position') {
            FinancePosition::firstOrCreate(
                ['name' => $type['name']],
                [
                    'code' => $type['code'] ?: null,
                    'finance_department_id' => $type['finance_department_id'] ?? null,
                    'salary_grade' => $type['salary_grade'] ?? null,
                    'status' => 'active',
                ]
            );
        } else {
            FinanceEmploymentType::firstOrCreate(
                ['name' => $type['name']],
                ['code' => $type['code'] ?: null, 'status' => 'active']
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
        $positions = FinancePosition::orderBy('name')->get();
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

    public function payrollIndex()
    {
        $payrolls = FinancePayrollPeriod::latest('period_month')->paginate(20);
        $activeStaffCount = FinanceStaff::where('status', 'active')->count();
        $activeSalaryTotal = (float) FinanceStaff::where('status', 'active')->sum('basic_salary');

        return view('admin.finance.payroll', compact('payrolls', 'activeStaffCount', 'activeSalaryTotal'));
    }

    public function payrollStore(Request $request)
    {
        $validated = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['period_month'])->startOfMonth();
        $activeStaff = FinanceStaff::where('status', 'active')->get(['id', 'basic_salary']);
        $gross = (float) $activeStaff->sum('basic_salary');

        $payroll = FinancePayrollPeriod::updateOrCreate([
            'period_month' => $month->toDateString(),
        ], [
            'name' => $month->format('F Y'),
            'status' => 'draft',
            'staff_count' => $activeStaff->count(),
            'gross_pay' => $gross,
            'total_deductions' => 0,
            'net_pay' => $gross,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('finance_payroll_created', 'FinancePayrollPeriod', $payroll->id, [
            'period' => $payroll->name,
            'staff_count' => $payroll->staff_count,
            'net_pay' => $payroll->net_pay,
        ]);

        return back()->with('success', 'Payroll period prepared from active staff salaries.');
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
        return 'STF-'.str_pad((string) (FinanceStaff::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT);
    }
}
