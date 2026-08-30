<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subs = $request->user()
            ->subscriptions()
            ->with(['product:id,name,slug', 'plan:id,name'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $subs->map(fn (Subscription $s) => $this->row($s))->all(),
        ]);
    }

    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        $subscription->load(['product:id,name,slug', 'plan:id,name']);

        return response()->json(['data' => $this->row($subscription)]);
    }

    private function row(Subscription $s): array
    {
        $expiry = $s->expiry_date;

        return [
            'id' => $s->id,
            'product' => $s->product?->name ?? '—',
            'plan' => $s->plan?->name,
            'status' => $s->status,
            'start_date' => optional($s->start_date)->toDateString(),
            'expiry_date' => optional($expiry)->toDateString(),
            'expires_in' => $expiry
                ? ($expiry->isPast() ? 'Expired' : $expiry->diffForHumans())
                : 'Lifetime',
        ];
    }
}
