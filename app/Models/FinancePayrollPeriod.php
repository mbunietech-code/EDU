<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancePayrollPeriod extends Model
{
    protected $fillable = [
        'name',
        'period_month',
        'status',
        'staff_count',
        'basic_pay',
        'total_allowances',
        'overtime_pay',
        'bonus_pay',
        'gross_pay',
        'taxable_pay',
        'total_deductions',
        'tax_amount',
        'pension_amount',
        'insurance_amount',
        'loan_deductions',
        'other_deductions',
        'net_pay',
        'employer_cost',
        'notes',
        'prepared_at',
        'approved_at',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'period_month' => 'date',
        'basic_pay' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'bonus_pay' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'taxable_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'pension_amount' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'loan_deductions' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'employer_cost' => 'decimal:2',
        'prepared_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(FinancePayrollItem::class, 'finance_payroll_period_id');
    }
}
