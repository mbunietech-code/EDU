<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->input('search');
                $query->where('name', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%");
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load(['orders.product', 'orders.plan', 'subscriptions.product', 'subscriptions.plan', 'payments']);

        return view('admin.users.show', compact('user'));
    }

    public function suspend(User $user)
    {
        $this->authorize('suspend', $user);

        $user->update(['status' => 'suspended']);

        \App\Models\ActivityLog::log(
            'user_suspended',
            'User',
            $user->id,
            ['email' => $user->email]
        );

        return back()->with('success', 'User suspended.');
    }

    public function activate(User $user)
    {
        $this->authorize('activate', $user);

        $user->update(['status' => 'active']);

        \App\Models\ActivityLog::log(
            'user_activated',
            'User',
            $user->id,
            ['email' => $user->email]
        );

        return back()->with('success', 'User activated.');
    }
}