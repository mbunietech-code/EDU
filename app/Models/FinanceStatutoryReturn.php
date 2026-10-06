<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceStatutoryReturn extends Model
{
    protected $fillable = [
        'finance_payroll_period_id',
        'type',
        'authority',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'reference',
        'notes',
        'paid_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancePayrollPeriod::class, 'finance_payroll_period_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function label(): string
    {
        return config("finance.statutory.returns.{$this->type}.label", strtoupper($this->type));
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date->isBefore(today());
    }
}
