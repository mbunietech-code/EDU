<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceStaff extends Model
{
    use SoftDeletes;

    protected $table = 'finance_staff';

    protected $fillable = [
        'staff_number',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'national_id',
        'phone',
        'alternative_phone',
        'email',
        'address',
        'region',
        'district',
        'emergency_contact',
        'emergency_contact_phone',
        'finance_department_id',
        'finance_position_id',
        'finance_employment_type_id',
        'hire_date',
        'status',
        'basic_salary',
        'bank_name',
        'bank_account_number',
        'mobile_money',
        'tax_number',
        'pension_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'basic_salary' => 'decimal:2',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(FinanceDepartment::class, 'finance_department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(FinancePosition::class, 'finance_position_id');
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(FinanceEmploymentType::class, 'finance_employment_type_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(FinanceStaffContract::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(FinancePayrollItem::class);
    }

    public function activeContract(): HasMany
    {
        return $this->contracts()->where('status', 'active')->latest('start_date');
    }

    /**
     * Most senior first (by position seniority), staff without a position last.
     */
    public function scopeOrderByRank(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE((SELECT seniority FROM finance_positions WHERE finance_positions.id = finance_staff.finance_position_id), 999)')
            ->orderBy('hire_date')
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->middle_name ? $this->middle_name.' ' : '').$this->last_name);
    }
}
