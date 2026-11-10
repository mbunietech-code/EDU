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

    /**
     * List filters shared by the web page and the app API.
     * status: all | attention | expired | soon | active | no_expiry.
     */
    public function scopeListFilter(Builder $q, string $status = 'all', string $category = '', string $search = ''): Builder
    {
        $today = Carbon::today();
        $soon = $today->copy()->addDays(self::SOON_DAYS);

        return $q
            ->when($category !== '' && isset(self::CATEGORIES[$category]), fn ($q) => $q->where('category', $category))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('vendor', 'like', '%'.$search.'%')
                ->orWhere('account', 'like', '%'.$search.'%')))
            ->when($status === 'attention', fn ($q) => $q->whereNotNull('expires_at')->whereDate('expires_at', '<=', $soon))
            ->when($status === 'expired', fn ($q) => $q->whereDate('expires_at', '<', $today))
            ->when($status === 'soon', fn ($q) => $q->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $soon))
            ->when($status === 'active', fn ($q) => $q->whereDate('expires_at', '>', $soon))
            ->when($status === 'no_expiry', fn ($q) => $q->whereNull('expires_at'))
            // Soonest end first; things that never end last.
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('name');
    }

    /** @return array{all:int, expired:int, soon:int} */
    public static function counts(): array
    {
        $today = Carbon::today();
        $soon = $today->copy()->addDays(self::SOON_DAYS);

        return [
            'all' => self::query()->count(),
            'expired' => self::query()->whereDate('expires_at', '<', $today)->count(),
            'soon' => self::query()->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $soon)->count(),
        ];
    }

    /** Validated asset fields from a web form or an app request. @return array<string,mixed> */
    public static function validatedFrom(\Illuminate\Http\Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', \Illuminate\Validation\Rule::in(array_keys(self::CATEGORIES))],
            'vendor' => ['nullable', 'string', 'max:150'],
            // Only web links: never javascript: or other schemes in a clickable link.
            'purchase_url' => ['nullable', 'url:http,https', 'max:500'],
            'account' => ['nullable', 'string', 'max:150'],
            'purchased_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:purchased_at'],
            'billing_cycle' => ['required', \Illuminate\Validation\Rule::in(array_keys(self::CYCLES))],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'auto_renew' => ['sometimes', 'boolean'],
            'responsible_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('is_admin', 1)->where('is_guest', 0)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['auto_renew'] = $request->boolean('auto_renew');

        return $data;
    }

    /** Admins who can look after an asset. */
    public static function admins()
    {
        return User::query()->where('is_admin', true)->where('is_guest', false)->orderBy('name')->get(['id', 'name', 'job_title']);
    }

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

    /** A new purchase or a renewal (purchase date moved) is recorded under Finance → Expenses by itself. */
    protected static function booted(): void
    {
        static::saved(function (self $asset) {
            if ($asset->wasRecentlyCreated || $asset->wasChanged('purchased_at')) {
                try {
                    app(\App\Services\Finance\PurchaseExpenseService::class)->autoRecord('asset', $asset);
                } catch (\Throwable $e) {
                    report($e); // never stop the item itself from saving
                }
            }
        });
    }
}
