<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancePayrollPeriod extends Model
{
    protected $fillable = [
        'name',
        'period_month',
        'status',
        'staff_count',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'created_by',
    ];

    protected $casts = [
        'period_month' => 'date',
        'gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];
}
