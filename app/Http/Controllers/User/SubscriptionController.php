<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Subscription;

class SubscriptionController extends Controller
{
    public function index()
    {
        $subscriptions = auth()->user()
            ->subscriptions()
            ->with(['product', 'plan', 'account'])
            ->latest()
            ->paginate(15);

        return view('user.subscriptions.index', compact('subscriptions'));
    }

    public function show(Subscription $subscription)
    {
        $this->authorize('view', $subscription);

        $subscription->load(['product', 'plan', 'account', 'order']);

        return view('user.subscriptions.show', compact('subscription'));
    }
}