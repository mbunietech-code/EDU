<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceExpense extends Model
{
    protected $table = 'finance_expenses';

    protected $fillable = [
        'money_account_id',
        'product_id',
        'tool_id',
        'label',
        'amount',
        'category',
        'description',
        'receipt_path',
        'spent_at',
        'created_by',
        'expense_type',
        'source_type',
        'source_id',
    ];

    private static ?bool $typesReady = null;

    /** @return array<string, array{label: string, category: string, default_label: ?string, product?: bool}> */
    public static function types(): array
    {
        return (array) config('finance.expense_types', []);
    }

    /** The 0035 database change is applied (expense_type / source columns exist). */
    public static function typesReady(): bool
    {
        if (self::$typesReady === null) {
            try {
                self::$typesReady = \Illuminate\Support\Facades\Schema::hasColumn('finance_expenses', 'expense_type');
            } catch (\Throwable $e) {
                self::$typesReady = false;
            }
        }

        return self::$typesReady;
    }

    public static function forgetTypesReady(): void
    {
        self::$typesReady = null;
    }

    /** Optional expense type rule for the forms (none before the database change). */
    public static function typeRules(): array
    {
        return self::typesReady()
            ? ['expense_type' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(self::types()))]]
            : [];
    }

    public function typeLabel(): ?string
    {
        return $this->expense_type ? (self::types()[$this->expense_type]['label'] ?? $this->expense_type) : null;
    }

    /** Created automatically (payroll, return, account, asset) rather than typed in. */
    public function isAutomatic(): bool
    {
        return filled($this->source_type);
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_at' => 'date',
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

    public function hasReceipt(): bool
    {
        return filled($this->receipt_path);
    }
}
