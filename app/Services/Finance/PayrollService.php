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

        $rows = $activeStaff->map(function (FinanceStaff $staff) use ($rates, $staffCount, $allowancePerStaff, $overtimePerStaff, $bonusPerStaff, $insurancePerStaff, $loanPerStaff, $otherDeductionPerStaff) {
            $basic = $this->money($staff->basic_salary);
            $gross = $basic + $allowancePerStaff + $overtimePerStaff + $bonusPerStaff;
            $s = $this->statutory->forStaff($basic, $gross, $staffCount, $rates);
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

    public function markReturnPaid(FinanceStatutoryReturn $return, array $validated, ?int $userId): void
    {
        $return->update($validated + ['status' => 'paid', 'paid_by' => $userId]);

        ActivityLog::log('finance_statutory_return_paid', 'FinanceStatutoryReturn', $return->id, [
            'type' => $return->type,
            'amount' => $return->amount,
            'reference' => $validated['reference'] ?? null,
        ]);
    }

    public function markReturnPending(FinanceStatutoryReturn $return): void
    {
        $return->update(['status' => 'pending', 'paid_at' => null, 'reference' => null, 'paid_by' => null]);

        ActivityLog::log('finance_statutory_return_reopened', 'FinanceStatutoryReturn', $return->id, ['type' => $return->type]);
    }

    protected function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}
