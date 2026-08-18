<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $subscriptions = $user->subscriptions()
            ->with(['product', 'plan'])
            ->latest()
            ->take(5)
            ->get();

        $activeSubscriptions = $user->subscriptions()
            ->with(['product', 'plan'])
            ->where('status', 'active')
            ->get();

        $pendingOrders = $user->orders()
            ->with(['product', 'plan'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('user.dashboard', compact('subscriptions', 'activeSubscriptions', 'pendingOrders'));
    }
}