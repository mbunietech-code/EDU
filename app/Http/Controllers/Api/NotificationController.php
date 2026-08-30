<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->paginate(30);

        return response()->json([
            'data' => collect($notifications->items())->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? class_basename($n->type),
                'message' => $n->data['message'] ?? 'Notification',
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
                'created_ago' => $n->created_at?->diffForHumans(),
                'order_id' => $n->data['order_id'] ?? null,
                'payment_id' => $n->data['payment_id'] ?? null,
            ])->all(),
            'meta' => [
                'unread' => $user->unreadNotifications()->count(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return response()->json(['message' => 'read']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'all read']);
    }
}
