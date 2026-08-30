<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::with(['user:id,name,email', 'latestMessage'])
            ->withCount(['messages as unread' => fn ($q) => $q
                ->where('is_from_admin', false)->where('is_read', false)])
            ->when($request->filled('search'), fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('email', 'like', '%'.$request->string('search').'%')))
            ->latest('updated_at')
            ->get();

        return response()->json([
            'data' => $conversations->map(fn (Conversation $c) => [
                'id' => $c->id,
                'user_name' => $c->user->name ?? '—',
                'user_email' => $c->user->email ?? '—',
                'unread' => $c->unread,
                'last_message' => $c->latestMessage?->body
                    ?? ($c->latestMessage ? '['.$c->latestMessage->type.']' : null),
                'last_from_admin' => (bool) ($c->latestMessage?->is_from_admin),
                'updated_ago' => optional($c->updated_at)->diffForHumans(),
            ])->all(),
            'meta' => ['unread_total' => $conversations->sum('unread')],
        ]);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $conversation->messages()
            ->where('is_from_admin', false)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        $messages = $conversation->messages()
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        $conversation->load('user:id,name,email');

        return response()->json([
            'conversation_id' => $conversation->id,
            'user_name' => $conversation->user->name ?? '—',
            'user_email' => $conversation->user->email ?? '—',
            'customer_online' => $conversation->user->isOnline(),
            'messages' => $messages->map(fn (ChatMessage $m) => $this->row($m))->all(),
        ]);
    }

    public function store(Request $request, Conversation $conversation, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = $conversation->messages()->create([
            'is_from_admin' => true,
            'type' => 'text',
            'body' => $data['body'],
        ]);

        $conversation->touch();

        try {
            $notifications->notifyUserNewChatMessage($conversation);
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
