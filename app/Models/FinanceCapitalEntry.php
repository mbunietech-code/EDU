<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCapitalEntry extends Model
{
    protected $table = 'finance_capital_entries';

    protected $fillable = [
        'product_id',
        'tool_id',
        'label',
        'amount',
        'source',
        'is_loan',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_loan' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
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
}
