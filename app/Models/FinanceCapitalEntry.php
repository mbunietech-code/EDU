<?php

namespace App\Models;

use App\Support\FinanceDates;
use App\Support\FinanceStart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCapitalEntry extends Model
{
    protected $table = 'finance_capital_entries';

    protected $fillable = [
        'money_account_id',
        'product_id',
        'tool_id',
        'label',
        'amount',
        'received_at',
        'source',
        'is_loan',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_loan' => 'boolean',
        'received_at' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    /** The money account ("mfuko") this went through; null = not assigned yet. */
    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceMoneyAccount::class, 'money_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(FinanceLoanRepayment::class)->latest('paid_at')->latest('id');
    }

    /**
     * Total repaid so far. Uses a preloaded withSum() value when present.
     */
    public function repaidAmount(): float
    {
        if (array_key_exists('repayments_sum_amount', $this->attributes)) {
            return (float) $this->attributes['repayments_sum_amount'];
        }

        return (float) $this->repayments()->sum('amount');
    }

    public function outstandingAmount(): float
    {
        if (! $this->is_loan) {
            return 0.0;
        }

        return max(0.0, round((float) $this->amount - $this->repaidAmount(), 2));
    }

    public function isFullyRepaid(): bool
    {
        return $this->is_loan && $this->outstandingAmount() <= 0;
    }

    /**
     * Entries in Finance's books, or with $old the unorganised ones before
     * them (FinanceStart): by the day received, else the day typed in.
     */
    public function scopeInBooks($query, bool $old = false)
    {
        if (! FinanceStart::isSet()) {
            return $old ? $query->whereRaw('1 = 0') : $query;
        }
        $day = FinanceStart::date()->toDateString();

        return $query->where(function ($w) use ($old, $day) {
            if (! FinanceDates::hasReceivedDate()) {
                FinanceStart::scopeMoment($w, 'created_at', $old);

                return;
            }
            $w->where('received_at', $old ? '<' : '>=', $day)
                ->orWhere(fn ($n) => FinanceStart::scopeMoment($n->whereNull('received_at'), 'created_at', $old));
        });
    }
}
