<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money already in an account, given to one line of the Finance overview as
 * its capital ("Give from available money"). Nothing leaves the account, so
 * the account balance and the company's total capital stay the same.
 */
class FinanceAllocation extends Model
{
    protected $table = 'finance_allocations';

    protected $fillable = [
        'money_account_id',
        'label',
        'product_id',
        'tool_id',
        'amount',
        'allocated_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocated_at' => 'date',
    ];

    private static ?bool $ready = null;

    /** The 0037 database change is applied. */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = \Illuminate\Support\Facades\Schema::hasTable('finance_allocations');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    public static function forgetReady(): void
    {
        self::$ready = null;
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceMoneyAccount::class, 'money_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
