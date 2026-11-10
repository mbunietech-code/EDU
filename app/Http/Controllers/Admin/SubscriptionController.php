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
            // Each value in the table is a link that adds one of these.
            ->adminFilter($request->only(['status', 'search', 'user', 'product', 'plan', 'account', 'device', 'expires', 'sort']))
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