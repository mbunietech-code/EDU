<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancePayrollItem extends Model
{
    protected $fillable = [
        'finance_payroll_period_id',
        'finance_staff_id',
        'staff_number',
        'staff_name',
        'department_name',
        'position_name',
        'basic_pay',
        'allowances',
        'overtime_pay',
        'bonus_pay',
        'gross_pay',
        'tax_amount',
        'pension_amount',
        'insurance_amount',
        'loan_deduction',
        'other_deduction',
        'total_deductions',
        'net_pay',
        'payment_channel',
        'bank_name',
        'bank_account_number',
        'mobile_money',
        'taxable_pay',
        'nssf_employer',
        'sdl_amount',
        'wcf_amount',
        'leave_provision',
        'severance_provision',
        'gratuity_provision',
        'employer_cost',
    ];

    protected $casts = [
        'basic_pay' => 'decimal:2',
        'allowances' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'bonus_pay' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'pension_amount' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'loan_deduction' => 'decimal:2',
        'other_deduction' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'taxable_pay' => 'decimal:2',
        'nssf_employer' => 'decimal:2',
        'sdl_amount' => 'decimal:2',
        'wcf_amount' => 'decimal:2',
        'leave_provision' => 'decimal:2',
        'severance_provision' => 'decimal:2',
        'gratuity_provision' => 'decimal:2',
        'employer_cost' => 'decimal:2',
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancePayrollPeriod::class, 'finance_payroll_period_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(FinanceStaff::class, 'finance_staff_id');
    }
}
