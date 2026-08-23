<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\CredentialService;

class SubscriptionController extends Controller
{
    public function __construct(protected CredentialService $credentialService)
    {
    }

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

        $credentials = null;
        $canViewCredentials = $subscription->account && ($subscription->isActive() || $subscription->status === 'expiring_soon');

        if ($canViewCredentials) {
            $credentials = $this->credentialService->decryptValue($subscription->account->credentials);
        }

        return view('user.subscriptions.show', compact('subscription', 'credentials'));
    }
}