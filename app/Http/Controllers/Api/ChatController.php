<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * The current user's support conversation with recent messages.
     */
    public function show(Request $request): JsonResponse
    {
        $conversation = Conversation::firstOrCreate(['user_id' => $request->user()->id]);

        // Mark incoming admin messages as read.
        $conversation->messages()
            ->where('is_from_admin', true)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        $messages = $conversation->messages()
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'conversation_id' => $conversation->id,
            'support_online' => User::anyAdminOnline(),
            'messages' => $messages->map(fn (ChatMessage $m) => $this->row($m))->all(),
        ]);
    }

    public function store(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $conversation = Conversation::firstOrCreate(['user_id' => $request->user()->id]);

        $message = $conversation->messages()->create([
            'is_from_admin' => false,
            'type' => 'text',
            'body' => $data['body'],
        ]);

        try {
            $notifications->notifyAdminsNewChatMessage($conversation);
        } catch (\Throwable $e) {
            // non-fatal
        }

        return response()->json(['data' => $this->row($message)]);
    }

    private function row(ChatMessage $m): array
    {
        return [
            'id' => $m->id,
            'from_admin' => (bool) $m->is_from_admin,
            'type' => $m->type,
            'body' => $m->body,
            'created_at' => optional($m->created_at)->toIso8601String(),
            'time' => optional($m->created_at)->format('H:i'),
        ];
    }
}
