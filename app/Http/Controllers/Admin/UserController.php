<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeletionService;
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
        $user->load(['orders.product', 'orders.plan', 'orders.tool', 'subscriptions.product', 'subscriptions.plan', 'payments']);

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

    public function destroy(Request $request, User $user, DeletionService $deletionService)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->is_admin) {
            return back()->with('error', 'Admin accounts cannot be deleted.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $deletionService->delete($user, $validated['reason']);

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}