<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    /** A customer may renew from this many days before the end. */
    public const RENEW_WINDOW_DAYS = 7;

    protected $fillable = [
        'user_id',
        'order_number',
        'product_id',
        'plan_id',
        'renews_order_id',
        'tool_id',
        'amount',
        'device',
        'status',
        'payment_instructions',
        'rejection_reason',
        'confirmed_at',
        'software_access_expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'software_access_expires_at' => 'datetime',
        'vpn_activated_at' => 'datetime',
        'vpn_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /** The order this one renews (null for a first purchase). */
    public function renewsOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'renews_order_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Order::class, 'renews_order_id');
    }

    public function isRenewal(): bool
    {
        return $this->renews_order_id !== null;
    }

    private static ?bool $renewalsReady = null;

    /**
     * Renewals need orders.renews_order_id (database/alters/0033). Until an
     * admin applies it from Admin → Database, renewing stays off and every
     * other page works as before.
     */
    public static function renewalsReady(): bool
    {
        if (self::$renewalsReady === null) {
            try {
                self::$renewalsReady = \Illuminate\Support\Facades\Schema::hasColumn('orders', 'renews_order_id');
            } catch (\Throwable $e) {
                self::$renewalsReady = false;
            }
        }

        return self::$renewalsReady;
    }

    /** Forget the cached column check (tests, or right after applying the alter). */
    public static function forgetRenewalsReady(): void
    {
        self::$renewalsReady = null;
    }

    /** Eager count for wasRenewed() on lists; does nothing before the alter. */
    public function scopeWithConfirmedRenewalsCount($query)
    {
        return self::renewalsReady()
            ? $query->withCount(['renewals as confirmed_renewals_count' => fn ($q) => $q->where('status', 'confirmed')])
            : $query;
    }

    /** A paid renewal took over this order's service. */
    public function wasRenewed(): bool
    {
        if (! self::renewalsReady()) {
            return false;
        }

        if (array_key_exists('confirmed_renewals_count', $this->attributes)) {
            return $this->confirmed_renewals_count > 0;
        }

        return $this->renewals()->where('status', 'confirmed')->exists();
    }

    /** The paid renewal that took over this order, if any. */
    public function confirmedRenewal(): ?Order
    {
        return self::renewalsReady()
            ? $this->renewals()->where('status', 'confirmed')->latest('id')->first()
            : null;
    }

    /** A renewal the customer started but has not paid for yet. */
    public function pendingRenewal(): ?Order
    {
        return self::renewalsReady()
            ? $this->renewals()->where('status', 'pending')->latest('id')->first()
            : null;
    }

    /** When the service bought with this order ends (null = never / unknown). */
    public function serviceEndsAt(): ?\Carbon\CarbonInterface
    {
        if ($this->product?->isVpn()) {
            return $this->vpn_expires_at;
        }

        $subscription = $this->relationLoaded('subscription') ? $this->subscription : $this->subscription()->first();

        return $subscription?->expiry_date;
    }

    /**
     * The customer can pay again to extend what this order bought: a confirmed
     * subscription or VPN order (not software or tools), not already renewed,
     * whose access ended or ends within RENEW_WINDOW_DAYS.
     */
    public function canBeRenewed(): bool
    {
        if (! self::renewalsReady()) {
            return false;
        }
        if (! $this->isConfirmed() || $this->isToolOrder() || ! $this->product || $this->product->isSoftware()) {
            return false;
        }
        if ($this->product->status !== 'published' || ! $this->product->plans()->active()->exists()) {
            return false;
        }
        $endsAt = $this->serviceEndsAt();
        if ($endsAt === null || $endsAt->gt(now()->addDays(self::RENEW_WINDOW_DAYS))) {
            return false;
        }

        return ! $this->wasRenewed();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function accessExpired(): bool
    {
        if (! $this->isConfirmed()) {
            return false;
        }

        if ($this->isSoftware()) {
            return $this->softwareAccessLocked();
        }

        $subscription = $this->relationLoaded('subscription')
            ? $this->subscription
            : $this->subscription()->first();

        return $subscription !== null
            && ($subscription->status === 'expired' || $subscription->isExpired());
    }

    public function displayStatus(): string
    {
        if ($this->isConfirmed() && $this->wasRenewed()) {
            return 'renewed';
        }

        if ($this->accessExpired()) {
            return 'expired';
        }

        return match ($this->status) {
            'confirmed', 'paid' => 'active',
            default => (string) $this->status,
        };
    }

    public function displayStatusLabel(): string
    {
        return match ($this->displayStatus()) {
            'active' => 'Active',
            'expired' => 'Expired',
            'renewed' => 'Renewed',
            default => ucwords(str_replace('_', ' ', (string) $this->status)),
        };
    }

    /**
     * A customer may cancel their own order only while it is still pending
     * and no payment has been approved. Confirmed orders can never be
     * cancelled by the customer.
     */
    public function canBeCancelledByCustomer(): bool
    {
        return $this->status === 'pending'
            && ! $this->payments()->where('status', 'approved')->exists();
    }

    public function isSoftware(): bool
    {
        return $this->product?->isSoftware() ?? false;
    }

    public function isToolOrder(): bool
    {
        return $this->tool_id !== null;
    }

    public function itemName(): string
    {
        return $this->tool?->name ?? $this->product?->name ?? 'N/A';
    }

    public function softwareAccessActive(): bool
    {
        return $this->isConfirmed()
            && $this->software_access_expires_at
            && $this->software_access_expires_at->isFuture();
    }

    public function softwareAccessLocked(): bool
    {
        return $this->isConfirmed()
            && ($this->software_access_expires_at === null || $this->software_access_expires_at->isPast());
    }
}
