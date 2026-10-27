<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'plan_name',
        'purchased_at',
        'expires_at',
        'cost',
        'cost_currency',
        'auto_renew',
        'description',
        'credentials',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'purchased_at' => 'date',
        'expires_at' => 'date',
        'cost' => 'decimal:2',
        'auto_renew' => 'boolean',
    ];

    public const CURRENCIES = ['TZS', 'USD', 'CNY', 'EUR', 'GBP', 'KES'];

    /** Validation for the plan we bought (shared by the web form and the app API). */
    public static function planRules(): array
    {
        return [
            'plan_name' => ['nullable', 'string', 'max:120'],
            'purchased_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:purchased_at'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'cost_currency' => ['nullable', 'string', 'in:'.implode(',', self::CURRENCIES)],
            'auto_renew' => ['nullable', 'boolean'],
        ];
    }

    /** Days before the end date that count as "ending soon". */
    public const EXPIRING_DAYS = 7;


    protected $hidden = [
        'credentials',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Free to hand to the next customer: available, and the plan we bought for
     * it has not ended (no end date recorded counts as still running).
     */
    public function scopeAssignable($query)
    {
        return $query->where('status', 'available')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString()));
    }

    /** Subscription statuses that mean someone is using the account now. */
    public const HOLDING_STATUSES = ['active', 'expiring_soon', 'suspended', 'pending'];

    /** The customer using this account now (latest holding subscription), if any. */
    public function currentSubscription(): ?Subscription
    {
        $subs = $this->relationLoaded('subscriptions') ? $this->subscriptions : $this->subscriptions()->with('user')->get();

        return $subs->whereIn('status', self::HOLDING_STATUSES)->sortByDesc('id')->first();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function assign(): void
    {
        $this->update(['status' => 'assigned']);
    }

    public function release(): void
    {
        $this->update(['status' => 'available']);
    }

    /** Not archived, soonest end date first (no end date last). */
    public function scopeForPlansPage($query)
    {
        return $query->with('product:id,name')
            ->withCount(['subscriptions as users_count' => fn ($q) => $q->whereIn('status', ['active', 'expiring_soon'])])
            ->where('status', '!=', 'archived')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderBy('name');
    }

    /** Whole days until the plan we bought ends (negative = already ended); null when no end date. */
    public function planDaysLeft(): ?int
    {
        return $this->expires_at ? (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false) : null;
    }

    /** active | expiring | expired | unknown (no end date recorded). */
    public function planState(): string
    {
        $days = $this->planDaysLeft();

        return match (true) {
            $days === null => 'unknown',
            $days < 0 => 'expired',
            $days <= self::EXPIRING_DAYS => 'expiring',
            default => 'active',
        };
    }

    /** A new purchase or a renewal (purchase date moved) is recorded under Finance → Expenses by itself. */
    protected static function booted(): void
    {
        static::saved(function (self $account) {
            if ($account->wasRecentlyCreated || $account->wasChanged('purchased_at')) {
                try {
                    app(\App\Services\Finance\PurchaseExpenseService::class)->autoRecord('account', $account);
                } catch (\Throwable $e) {
                    report($e); // never stop the item itself from saving
                }
            }
        });
    }
}
