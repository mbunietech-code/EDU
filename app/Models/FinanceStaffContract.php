<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceStaffContract extends Model
{
    protected $fillable = [
        'finance_staff_id',
        'finance_department_id',
        'finance_position_id',
        'finance_employment_type_id',
        'contract_number',
        'contract_type',
        'start_date',
        'end_date',
        'probation_end_date',
        'basic_salary',
        'salary_grade',
        'leave_entitlement_days',
        'status',
        'renewal_date',
        'termination_date',
        'termination_reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'probation_end_date' => 'date',
        'renewal_date' => 'date',
        'termination_date' => 'date',
        'basic_salary' => 'decimal:2',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(FinanceStaff::class, 'finance_staff_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(FinanceDepartment::class, 'finance_department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(FinancePosition::class, 'finance_position_id');
    }
}
