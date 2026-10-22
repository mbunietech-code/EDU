<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place money sits ("mfuko"): Alipay, WeChat Pay, MIX by YAS, a bank
 * account, petty cash… Customer payments land here by their payment method
 * code; expenses, capital and loan repayments name it directly.
 */
class FinanceMoneyAccount extends Model
{
    protected $table = 'finance_money_accounts';

    public const TYPES = [
        'mobile_money' => 'Mobile money',
        'bank' => 'Bank',
        'wallet' => 'Online wallet',
        'cash' => 'Cash / petty cash',
    ];

    protected $fillable = [
        'name',
        'type',
        'number',
        'opening_balance',
        'opening_date',
        'payment_methods',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_date' => 'date',
        'payment_methods' => 'array',
        'is_active' => 'boolean',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(FinanceExpense::class, 'money_account_id');
    }

    public function capitalEntries(): HasMany
    {
        return $this->hasMany(FinanceCapitalEntry::class, 'money_account_id');
    }

    public function loanRepayments(): HasMany
    {
        return $this->hasMany(FinanceLoanRepayment::class, 'money_account_id');
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(FinanceMoneyTransfer::class, 'from_account_id');
    }

    public function transfersIn(): HasMany
    {
        return $this->hasMany(FinanceMoneyTransfer::class, 'to_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    /** @return list<string> payment method codes whose approved payments land here */
    public function methodCodes(): array
    {
        return array_values(array_filter((array) $this->payment_methods));
    }
}
