<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceLoanRepayment extends Model
{
    protected $table = 'finance_loan_repayments';

    protected $fillable = [
        'finance_capital_entry_id',
        'amount',
        'paid_at',
        'method',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function capitalEntry(): BelongsTo
    {
        return $this->belongsTo(FinanceCapitalEntry::class, 'finance_capital_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
