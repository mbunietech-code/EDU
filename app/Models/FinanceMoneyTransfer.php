<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money moved between two money accounts (e.g. Alipay → CRDB, CRDB → petty cash). */
class FinanceMoneyTransfer extends Model
{
    protected $table = 'finance_money_transfers';

    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'amount',
        'transferred_at',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transferred_at' => 'date',
    ];

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceMoneyAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceMoneyAccount::class, 'to_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
