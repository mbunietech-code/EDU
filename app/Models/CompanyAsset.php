<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Something Mbunie pays for: a domain, hosting, a server, software, a
 * licence, a device… with when it was bought, when it ends, where it is
 * bought and who looks after it.
 */
class CompanyAsset extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'domain' => 'Domain',
        'hosting' => 'Hosting',
        'server' => 'Server / VPS',
        'software' => 'Software / app',
        'subscription' => 'Online subscription',
        'license' => 'Licence',
        'device' => 'Device / equipment',
        'service' => 'Service',
        'other' => 'Other',
    ];

    public const CYCLES = [
        'one_time' => 'One-time',
        'monthly' => 'Monthly',
        'quarterly' => 'Every 3 months',
        'yearly' => 'Yearly',
        'two_years' => 'Every 2 years',
    ];

    /** "Ends soon" window. */
    public const SOON_DAYS = 30;

    protected $fillable = [
        'name', 'category', 'vendor', 'purchase_url', 'account',
        'purchased_at', 'expires_at', 'billing_cycle', 'cost', 'currency',
        'auto_renew', 'responsible_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'expires_at' => 'date',
        'cost' => 'decimal:2',
        'auto_renew' => 'boolean',
        'reminded_days' => 'integer',
    ];

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Days until it ends (negative = already ended), or null when it never ends. */
    public function daysLeft(): ?int
    {
        return $this->expires_at ? (int) Carbon::today()->diffInDays($this->expires_at, false) : null;
    }

    /** expired | soon | active | no_expiry */
    public function state(): string
    {
        $days = $this->daysLeft();

        return match (true) {
            $days === null => 'no_expiry',
            $days < 0 => 'expired',
            $days <= self::SOON_DAYS => 'soon',
            default => 'active',
        };
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function cycleLabel(): string
    {
        return self::CYCLES[$this->billing_cycle] ?? ucfirst((string) $this->billing_cycle);
    }

    /** Next end date after a renewal on $from (null for one-time purchases). */
    public function nextExpiry(Carbon $from): ?Carbon
    {
        $base = $this->expires_at && $this->expires_at->isFuture() ? $this->expires_at->copy() : $from->copy();

        return match ($this->billing_cycle) {
            'monthly' => $base->addMonthNoOverflow(),
            'quarterly' => $base->addMonthsNoOverflow(3),
            'yearly' => $base->addYearNoOverflow(),
            'two_years' => $base->addYearsNoOverflow(2),
            default => null,
        };
    }

    public function scopeEndingWithin(Builder $q, int $days): Builder
    {
        return $q->whereNotNull('expires_at')->whereDate('expires_at', '<=', Carbon::today()->addDays($days));
    }
}
