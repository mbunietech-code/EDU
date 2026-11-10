<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'plan_id',
        'account_id',
        'order_id',
        'start_date',
        'expiry_date',
        'status',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expiry_date' => 'date',
        'cancelled_at' => 'datetime',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiringSoon($query)
    {
        return $query->where('status', 'expiring_soon');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return now()->gt($this->expiry_date);
    }

    public function daysRemaining(): int
    {
        return (int) now()->diffInDays($this->expiry_date, false);
    }

    public function isExpiringSoon(int $thresholdDays = 3): bool
    {
        return $this->daysRemaining() <= $thresholdDays && $this->isActive();
    }

    /**
     * Admin list filters, shared by admin/subscriptions and the app API:
     * status (incl. expiring_soon by date), search, user, product, plan,
     * account (id or "none"), device (name or "none"), expires
     * (past | 7d | 30d | later | Y-m-d) and sort (expires_asc | expires_desc).
     */
    public function scopeAdminFilter($query, array $f)
    {
        $id = fn (string $key) => ctype_digit((string) ($f[$key] ?? '')) && (int) $f[$key] > 0 ? (int) $f[$key] : null;
        $tz = config('app.timezone');
        $today = now($tz)->toDateString();

        return $query
            ->when(($f['status'] ?? '') !== '', function ($query) use ($f, $tz, $today) {
                if ($f['status'] === 'expiring_soon') {
                    // Date-based, so it works even when the daily expiry job has not run.
                    $query->whereIn('status', ['active', 'expiring_soon'])
                        ->whereDate('expiry_date', '>=', $today)
                        ->whereDate('expiry_date', '<=', now($tz)->addDays((int) config('app.expiry_warning_days', 3))->toDateString());

                    return;
                }
                $query->where('status', $f['status']);
            })
            ->when(($f['search'] ?? '') !== '', function ($query) use ($f) {
                $term = $f['search'];
                $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
            })
            ->when($id('user'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($id('product'), fn ($q, $v) => $q->where('product_id', $v))
            ->when($id('plan'), fn ($q, $v) => $q->where('plan_id', $v))
            ->when(($f['account'] ?? '') === 'none', fn ($q) => $q->whereNull('account_id'))
            ->when($id('account'), fn ($q, $v) => $q->where('account_id', $v))
            ->when(($f['device'] ?? '') !== '', function ($q) use ($f) {
                $device = (string) $f['device'];
                $device === 'none'
                    ? $q->whereDoesntHave('order', fn ($o) => $o->whereNotNull('device')->where('device', '!=', ''))
                    : $q->whereHas('order', fn ($o) => $o->where('device', $device));
            })
            ->when((string) ($f['expires'] ?? ''), fn ($q, $window) => match (true) {
                $window === 'past' => $q->whereDate('expiry_date', '<', $today),
                $window === '7d' => $q->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', now($tz)->addDays(7)->toDateString()),
                $window === '30d' => $q->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', now($tz)->addDays(30)->toDateString()),
                $window === 'later' => $q->whereDate('expiry_date', '>', now($tz)->addDays(30)->toDateString()),
                (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $window) => $q->whereDate('expiry_date', $window),
                default => $q,
            })
            ->when(true, fn ($q) => match ($f['sort'] ?? null) {
                'expires_asc' => $q->orderBy('expiry_date')->latest('id'),
                'expires_desc' => $q->orderByDesc('expiry_date')->latest('id'),
                default => $q->latest(),
            });
    }
}
