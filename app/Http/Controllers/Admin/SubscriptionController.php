<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\NotificationService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected NotificationService $notificationService,
    ) {
    }

    public function index(Request $request)
    {
        $subscriptions = Subscription::with(['user', 'product', 'plan', 'order:id,device'])
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->input('status') === 'expiring_soon') {
                    // Date-based, so it works even when the daily expiry job
                    // (which flips status to expiring_soon) hasn't run — e.g.
                    // no cron on shared hosting. Same window the job uses.
                    $today = now(config('app.timezone'))->toDateString();
                    $until = now(config('app.timezone'))
                        ->addDays((int) config('app.expiry_warning_days', 3))
                        ->toDateString();

                    $query->whereIn('status', ['active', 'expiring_soon'])
                        ->whereDate('expiry_date', '>=', $today)
                        ->whereDate('expiry_date', '<=', $until);

                    return;
                }

                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->input('search');
                $query->whereHas('user', function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%");
                });
            })
            // Each value in the table is a link that adds one of these.
            ->when($this->id($request, 'user'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($this->id($request, 'product'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($this->id($request, 'plan'), fn ($q, $id) => $q->where('plan_id', $id))
            ->when($request->input('account') === 'none', fn ($q) => $q->whereNull('account_id'))
            ->when($this->id($request, 'account'), fn ($q, $id) => $q->where('account_id', $id))
            ->when($request->filled('device'), function ($q) use ($request) {
                $device = (string) $request->input('device');
                $device === 'none'
                    ? $q->whereDoesntHave('order', fn ($o) => $o->whereNotNull('device')->where('device', '!=', ''))
                    : $q->whereHas('order', fn ($o) => $o->where('device', $device));
            })
            ->when($request->input('expires'), fn ($q, $window) => $this->filterExpiry($q, (string) $window))
            ->when(true, fn ($q) => match ($request->input('sort')) {
                'expires_asc' => $q->orderBy('expiry_date')->latest('id'),
                'expires_desc' => $q->orderByDesc('expiry_date')->latest('id'),
                default => $q->latest(),
            })
            ->paginate(15)
            ->withQueryString();

        $products = \App\Models\Product::orderBy('name')->get(['id', 'name']);
        $plans = $this->id($request, 'product')
            ? \App\Models\Plan::where('product_id', $this->id($request, 'product'))->orderBy('sort_order')->get(['id', 'name'])
            : collect();
        $devices = \App\Models\Order::whereNotNull('device')->where('device', '!=', '')
            ->whereIn('id', Subscription::whereNotNull('order_id')->select('order_id'))
            ->distinct()->orderBy('device')->pluck('device');
        $filterUser = $this->id($request, 'user') ? \App\Models\User::find($this->id($request, 'user'), ['id', 'name']) : null;

        return view('admin.subscriptions.index', compact('subscriptions', 'products', 'plans', 'devices', 'filterUser'));
    }

    /** Positive integer query value, or null. */
    private function id(Request $request, string $key): ?int
    {
        $value = (string) $request->input($key, '');

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** Expiry windows, or one exact day (Y-m-d) when a date in the table is clicked. */
    private function filterExpiry($query, string $window)
    {
        $tz = config('app.timezone');
        $today = now($tz)->toDateString();

        return match (true) {
            $window === 'past' => $query->whereDate('expiry_date', '<', $today),
            $window === '7d' => $query->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', now($tz)->addDays(7)->toDateString()),
            $window === '30d' => $query->whereDate('expiry_date', '>=', $today)->whereDate('expiry_date', '<=', now($tz)->addDays(30)->toDateString()),
            $window === 'later' => $query->whereDate('expiry_date', '>', now($tz)->addDays(30)->toDateString()),
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $window) => $query->whereDate('expiry_date', $window),
            default => $query,
        };
    }

    public function show(Subscription $subscription)
    {
        $this->authorize('view', $subscription);

        $subscription->load(['user', 'product', 'plan', 'account', 'order']);

        return view('admin.subscriptions.show', compact('subscription'));
    }

    public function extend(Request $request, Subscription $subscription)
    {
        $this->authorize('extend', $subscription);

        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $this->subscriptionService->extend($subscription, $validated['days']);

        return back()->with('success', 'Subscription extended by ' . $validated['days'] . ' days.');
    }

    public function suspend(Subscription $subscription)
    {
        $this->authorize('suspend', $subscription);

        $subscription->update(['status' => 'suspended']);

        \App\Models\ActivityLog::log(
            'subscription_suspended',
            'Subscription',
            $subscription->id
        );

        return back()->with('success', 'Subscription suspended.');
    }

    public function revoke(Subscription $subscription)
    {
        $this->authorize('revoke', $subscription);

        $subscription->update(['status' => 'revoked']);

        if ($subscription->account && $subscription->account->status === 'assigned') {
            $subscription->account->release();
        }

        \App\Models\ActivityLog::log(
            'subscription_revoked',
            'Subscription',
            $subscription->id,
            ['account_id' => $subscription->account_id]
        );

        $this->notificationService->notifyUserAccessRevoked($subscription->user, $subscription);

        return back()->with('success', 'Subscription revoked and account released.');
    }

    public function expire(Subscription $subscription)
    {
        $this->authorize('expire', $subscription);

        $this->subscriptionService->expireSubscriptionAndReleaseAccount($subscription);

        return back()->with('success', 'Subscription expired and account released.');
    }
}