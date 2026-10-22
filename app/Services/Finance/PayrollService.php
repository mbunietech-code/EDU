<?php

namespace App\Services\Finance;

use App\Models\ActivityLog;
use App\Models\FinancePayrollItem;
use App\Models\FinancePayrollPeriod;
use App\Models\FinanceStaff;
use App\Models\FinanceStatutoryReturn;
use App\Services\StatutoryPayrollService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payroll drafts with Tanzania statutory deductions and the statutory
 * returns they create. Shared by the web Finance pages and the app API.
 */
class PayrollService
{
    public const RULES = [
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
    ];

    public const RETURN_PAID_RULES = [
        'paid_at' => ['required', 'date', 'before_or_equal:today'],
        'reference' => ['nullable', 'string', 'max:120'],
        'notes' => ['nullable', 'string', 'max:2000'],
    ];

    public function __construct(protected StatutoryPayrollService $statutory)
    {
    }

    /**
     * Prepare (or regenerate) the draft payroll for a month.
     *
     * @throws FinanceException
     */
    public function prepare(array $validated, ?int $userId): FinancePayrollPeriod
    {
        $month = Carbon::createFromFormat('Y-m', $validated['period_month'])->startOfMonth();
        $existing = FinancePayrollPeriod::whereDate('period_month', $month->toDateString())->first();

        if ($existing && $existing->status !== 'draft') {
            throw new FinanceException("{$existing->name} payroll is already {$existing->status} and cannot be regenerated.");
        }

        $activeStaff = FinanceStaff::with(['department', 'position'])
            ->where('status', 'active')
            ->orderByRank()
            ->get();

        if ($activeStaff->isEmpty()) {
            throw new FinanceException('Add active staff before preparing payroll.');
        }

        $staffCount = $activeStaff->count();
        $rates = $this->statutory->rates();
        $allowancePerStaff = $this->money($validated['transport_allowance'] ?? 0)
            + $this->money($validated['meal_allowance'] ?? 0)
            + $this->money($validated['housing_allowance'] ?? 0);
        $overtimePerStaff = $this->money($validated['overtime_pay'] ?? 0);
        $bonusPerStaff = $this->money($validated['bonus_pay'] ?? 0);
        $insurancePerStaff = $this->money($validated['insurance_amount'] ?? 0);
        $loanPerStaff = $this->money($validated['loan_deductions'] ?? 0);
        $otherDeductionPerStaff = $this->money($validated['other_deductions'] ?? 0);

        $rows = $activeStaff->map(fn (FinanceStaff $staff) => [
            'finance_staff_id' => $staff->id,
            'staff_number' => $staff->staff_number,
            'staff_name' => $staff->fullName(),
            'department_name' => $staff->department?->name,
            'position_name' => $staff->position?->name,
            'payment_channel' => $staff->bank_account_number ? 'bank' : ($staff->mobile_money ? 'mobile_money' : null),
            'bank_name' => $staff->bank_name,
            'bank_account_number' => $staff->bank_account_number,
            'mobile_money' => $staff->mobile_money,
        ] + $this->computeLine([
            'basic_pay' => $staff->basic_salary,
            'allowances' => $allowancePerStaff,
            'overtime_pay' => $overtimePerStaff,
            'bonus_pay' => $bonusPerStaff,
            'insurance_amount' => $insurancePerStaff,
            'loan_deduction' => $loanPerStaff,
            'other_deduction' => $otherDeductionPerStaff,
        ], $staffCount, $rates));

        $totals = $this->totals($rows);

        $payroll = DB::transaction(function () use ($existing, $month, $staffCount, $totals, $validated, $rows, $userId) {
            $payroll = $existing ?? new FinancePayrollPeriod(['period_month' => $month->toDateString()]);
            $payroll->fill($totals + [
                'name' => $month->format('F Y'),
                'status' => 'draft',
                'staff_count' => $staffCount,
                'notes' => $validated['notes'] ?? null,
                'prepared_at' => now(),
                'created_by' => $userId,
            ])->save();

            FinancePayrollItem::where('finance_payroll_period_id', $payroll->id)->delete();
            $payroll->items()->createMany($rows->all());

            $this->syncStatutoryReturns($payroll, $month);

            return $payroll;
        });

        ActivityLog::log('finance_payroll_created', 'FinancePayrollPeriod', $payroll->id, [
            'period' => $payroll->name,
            'staff_count' => $payroll->staff_count,
            'net_pay' => $payroll->net_pay,
        ]);

        return $payroll;
    }

    /**
     * One staff line: earnings in, statutory deductions and employer costs out.
     *
     * @param  array{basic_pay: mixed, allowances: mixed, overtime_pay: mixed, bonus_pay: mixed, insurance_amount: mixed, loan_deduction: mixed, other_deduction: mixed}  $in
     */
    public function computeLine(array $in, int $staffCount, ?array $rates = null): array
    {
        $rates ??= $this->statutory->rates();
        $basic = $this->money($in['basic_pay']);
        $allowances = $this->money($in['allowances']);
        $overtime = $this->money($in['overtime_pay']);
        $bonus = $this->money($in['bonus_pay']);
        $insurance = $this->money($in['insurance_amount']);
        $loan = $this->money($in['loan_deduction']);
        $other = $this->money($in['other_deduction']);

        $gross = $basic + $allowances + $overtime + $bonus;
        $s = $this->statutory->forStaff($basic, $gross, $staffCount, $rates);
        $deductions = $s['paye'] + $s['nssf_employee'] + $insurance + $loan + $other;

        return [
            'basic_pay' => $basic,
            'allowances' => $allowances,
            'overtime_pay' => $overtime,
            'bonus_pay' => $bonus,
            'gross_pay' => $gross,
            'taxable_pay' => $s['taxable_pay'],
            'tax_amount' => $s['paye'],
            'pension_amount' => $s['nssf_employee'],
            'insurance_amount' => $insurance,
            'loan_deduction' => $loan,
            'other_deduction' => $other,
            'total_deductions' => $deductions,
            'net_pay' => max(0, $gross - $deductions),
            'nssf_employer' => $s['nssf_employer'],
            'sdl_amount' => $s['sdl'],
            'wcf_amount' => $s['wcf'],
            'leave_provision' => $s['leave_provision'],
            'severance_provision' => $s['severance_provision'],
            'gratuity_provision' => $s['gratuity_provision'],
            'employer_cost' => $s['employer_cost'],
        ];
    }

    /** Period totals from its staff lines. */
    protected function totals($rows): array
    {
        $rows = collect($rows);
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

        return $totals;
    }

    public const ITEM_RULES = [
        'allowances' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        'overtime_pay' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        'bonus_pay' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        'insurance_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        'loan_deduction' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        'other_deduction' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
    ];

    /**
     * Change one person's allowances, overtime, bonus or deductions on a draft
     * (posho kwa mtu mmoja), then refresh the period totals and its returns.
     *
     * @throws FinanceException
     */
    public function updateItem(FinancePayrollItem $item, array $validated): FinancePayrollItem
    {
        $payroll = $item->payrollPeriod;
        if ($payroll->status !== 'draft') {
            throw new FinanceException("{$payroll->name} payroll is {$payroll->status}; reopen it as a draft to change a line.");
        }

        DB::transaction(function () use ($item, $validated, $payroll) {
            $item->update($this->computeLine([
                'basic_pay' => $item->basic_pay,
                'allowances' => $validated['allowances'] ?? 0,
                'overtime_pay' => $validated['overtime_pay'] ?? 0,
                'bonus_pay' => $validated['bonus_pay'] ?? 0,
                'insurance_amount' => $validated['insurance_amount'] ?? 0,
                'loan_deduction' => $validated['loan_deduction'] ?? 0,
                'other_deduction' => $validated['other_deduction'] ?? 0,
            ], (int) $payroll->staff_count));

            $payroll->update($this->totals($payroll->items()->get()->map->getAttributes()));
            $this->syncStatutoryReturns($payroll, $payroll->period_month->copy()->startOfMonth());
        });

        ActivityLog::log('finance_payroll_line_updated', 'FinancePayrollItem', $item->id, [
            'period' => $payroll->name, 'staff' => $item->staff_name, 'net_pay' => $item->net_pay,
        ]);

        return $item->fresh();
    }

    /** @throws FinanceException */
    public function approve(FinancePayrollPeriod $payroll): void
    {
        if ($payroll->status !== 'draft') {
            throw new FinanceException("{$payroll->name} payroll is already {$payroll->status}.");
        }
        $payroll->update(['status' => 'approved', 'approved_at' => now()]);
        ActivityLog::log('finance_payroll_approved', 'FinancePayrollPeriod', $payroll->id, ['period' => $payroll->name, 'net_pay' => $payroll->net_pay]);
    }

    /** Back to draft to fix a line. Paid payrolls stay paid. @throws FinanceException */
    public function reopen(FinancePayrollPeriod $payroll): void
    {
        if ($payroll->status !== 'approved') {
            throw new FinanceException("Only an approved payroll can go back to draft ({$payroll->name} is {$payroll->status}).");
        }
        $payroll->update(['status' => 'draft', 'approved_at' => null]);
        ActivityLog::log('finance_payroll_reopened', 'FinancePayrollPeriod', $payroll->id, ['period' => $payroll->name]);
    }

    public static function paidRules(): array
    {
        return MoneyAccountService::fieldRules() + [
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Salaries went out: the payroll becomes paid and its net pay is recorded
     * once as an expense, from the chosen money account. PAYE and NSSF were
     * deducted and are paid later through Returns, which record their own.
     *
     * @throws FinanceException
     */
    public function markPaid(FinancePayrollPeriod $payroll, array $validated, ?int $userId): void
    {
        if ($payroll->status !== 'approved') {
            throw new FinanceException("Approve {$payroll->name} payroll before marking it paid.");
        }

        DB::transaction(function () use ($payroll, $validated, $userId) {
            $payroll->update(['status' => 'paid', 'paid_at' => $validated['paid_at']]);

            if ((float) $payroll->net_pay > 0) {
                app(AutoExpenseService::class)->record('payroll', $payroll->id, [
                    'label' => "Salaries – {$payroll->name}",
                    'amount' => (float) $payroll->net_pay,
                    'spent_at' => $validated['paid_at'],
                    'expense_type' => 'salaries',
                    'description' => "Net pay for {$payroll->staff_count} staff. PAYE, NSSF, SDL and WCF are recorded when paid under Returns.",
                    'money_account_id' => $validated['money_account_id'] ?? null,
                ], $userId);
            }
        });

        ActivityLog::log('finance_payroll_paid', 'FinancePayrollPeriod', $payroll->id, ['period' => $payroll->name, 'net_pay' => $payroll->net_pay]);
    }

    /**
     * Create or refresh the monthly PAYE / SDL / NSSF / WCF remittances for a
     * payroll period. Returns already marked paid are never changed.
     */
    protected function syncStatutoryReturns(FinancePayrollPeriod $payroll, Carbon $month): void
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
                'due_date' => $this->statutory->dueDate($type, $month)->toDateString(),
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Validation rules for the statutory rate settings.
     */
    public function rateRules(): array
    {
        $rules = [];
        foreach (array_keys(config('finance.statutory.rates')) as $key) {
            $rules[$key] = $key === 'sdl_min_staff'
                ? ['required', 'integer', 'min:0', 'max:100000']
                : ['required', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }

    public function saveRates(array $validated): void
    {
        $this->statutory->saveRates($validated);

        ActivityLog::log('finance_statutory_settings_updated', 'Finance', null, $validated);
    }

    public function returnsSummary(): array
    {
        return [
            'overdue' => (float) FinanceStatutoryReturn::where('status', '!=', 'paid')->whereDate('due_date', '<', today())->sum('amount'),
            'pending' => (float) FinanceStatutoryReturn::where('status', '!=', 'paid')->whereDate('due_date', '>=', today())->sum('amount'),
            'paid_this_year' => (float) FinanceStatutoryReturn::where('status', 'paid')->whereYear('paid_at', now()->year)->sum('amount'),
            'provisions' => (float) FinancePayrollPeriod::sum('total_provisions'),
            'leave_provision' => (float) FinancePayrollPeriod::sum('leave_provision'),
            'severance_provision' => (float) FinancePayrollPeriod::sum('severance_provision'),
            'gratuity_provision' => (float) FinancePayrollPeriod::sum('gratuity_provision'),
        ];
    }

    /** RETURN_PAID_RULES plus which money account paid it (once that exists). */
    public static function returnPaidRules(): array
    {
        return MoneyAccountService::fieldRules() + self::RETURN_PAID_RULES;
    }

    /**
     * A PAYE / SDL / NSSF / WCF remittance was paid: mark it and record the
     * payment once as an expense, from the chosen money account.
     */
    public function markReturnPaid(FinanceStatutoryReturn $return, array $validated, ?int $userId): void
    {
        $moneyAccountId = $validated['money_account_id'] ?? null;
        unset($validated['money_account_id']);

        DB::transaction(function () use ($return, $validated, $userId, $moneyAccountId) {
            $return->update($validated + ['status' => 'paid', 'paid_by' => $userId]);

            $period = $return->payrollPeriod?->name;
            app(AutoExpenseService::class)->record('statutory_return', $return->id, [
                'label' => trim('Payroll taxes – '.$return->label().' '.$period),
                'amount' => (float) $return->amount,
                'spent_at' => $validated['paid_at'],
                'expense_type' => 'payroll_taxes',
                'description' => trim(($return->authority ? "Paid to {$return->authority}. " : '').($validated['reference'] ?? '')),
                'money_account_id' => $moneyAccountId,
            ], $userId);
        });

        ActivityLog::log('finance_statutory_return_paid', 'FinanceStatutoryReturn', $return->id, [
            'type' => $return->type,
            'amount' => $return->amount,
            'reference' => $validated['reference'] ?? null,
        ]);
    }

    /** Undo "paid": the return is owed again and its automatic expense goes away. */
    public function markReturnPending(FinanceStatutoryReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $return->update(['status' => 'pending', 'paid_at' => null, 'reference' => null, 'paid_by' => null]);
            app(AutoExpenseService::class)->forget('statutory_return', $return->id);
        });

        ActivityLog::log('finance_statutory_return_reopened', 'FinanceStatutoryReturn', $return->id, ['type' => $return->type]);
    }

    protected function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}
