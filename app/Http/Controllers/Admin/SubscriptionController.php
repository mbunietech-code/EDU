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
        $subscriptions = Subscription::with(['user', 'product', 'plan'])
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
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.subscriptions.index', compact('subscriptions'));
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