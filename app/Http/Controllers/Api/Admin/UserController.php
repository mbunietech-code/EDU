<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->withCount(['orders', 'subscriptions as active_subscriptions_count' => fn ($q) => $q->where('status', 'active')])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($users->items())->map(fn (User $u) => $this->row($u))->all(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->loadCount(['orders', 'payments']);
        $user->load([
            'subscriptions' => fn ($q) => $q->with('product:id,name')->latest()->limit(10),
            'orders' => fn ($q) => $q->with('product:id,name')->latest()->limit(10),
        ]);

        return response()->json([
            'data' => array_merge($this->row($user), [
                'role' => $user->roleName(),
                'created_at' => optional($user->created_at)->toIso8601String(),
                'subscriptions' => $user->subscriptions->map(fn ($s) => [
                    'product' => $s->product->name ?? '—',
                    'status' => $s->status,
                    'ends_at' => optional($s->expiry_date)->toDateString(),
                ])->all(),
                'orders' => $user->orders->map(fn ($o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'title' => $o->product->name ?? 'Order',
                    'status' => $o->status,
                    'amount_label' => 'TZS '.number_format((float) $o->amount),
                ])->all(),
            ]),
        ]);
    }

    public function setStatus(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot change your own status.'], 422);
        }

        $user->update(['status' => $data['status']]);
        ActivityLog::log('user_'.($data['status'] === 'active' ? 'activated' : 'suspended'), 'User', $user->id, [
            'email' => $user->email,
        ]);

        return response()->json(['data' => $this->row($user->fresh()), 'message' => 'User updated.']);
    }

    private function row(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'status' => $u->status ?? 'active',
            'is_admin' => (bool) $u->is_admin,
            'orders_count' => $u->orders_count ?? 0,
            'active_subscriptions_count' => $u->active_subscriptions_count ?? 0,
        ];
    }
}
