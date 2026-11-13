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
        $search = trim((string) $request->input('search', ''));
        $status = in_array($request->input('status'), Conversation::INBOX_STATUSES, true)
            ? $request->input('status') : 'all';
        $period = in_array($request->input('period'), Conversation::INBOX_PERIODS, true)
            ? $request->input('period') : null;

        $query = Conversation::query()
            ->adminInbox($search, $period)
            ->inboxStatus($status)
            ->with(['user:id,name,email,last_seen_at', 'latestMessage'])
            ->withCount(['messages as unread' => fn ($q) => $q
                ->where('is_from_admin', false)->where('is_read', false)])
            ->latest('updated_at');

        $unreadTotal = ChatMessage::query()
            ->where('is_from_admin', false)
            ->where('is_read', false)
            ->whereHas('conversation', fn ($q) => $q->adminInbox())
            ->count();

        $meta = [
            'unread_total' => $unreadTotal,
            'counts' => Conversation::inboxCounts($search, $period),
            'status' => $status,
            'period' => $period,
        ];

        // App 1.2.0 and older send no page and expect the whole list; newer
        // builds ask page by page.
        if ($request->filled('page')) {
            $page = $query->paginate(min(50, max(5, (int) $request->input('per_page', 20))));
            $conversations = collect($page->items());
            $meta += [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ];
        } else {
            $conversations = $query->get();
        }

        return response()->json([
            'data' => $conversations->map(fn (Conversation $c) => [
                'id' => $c->id,
                'user_name' => $c->user->name ?? '—',
                'user_email' => $c->user->email ?? '—',
                'online' => (bool) $c->user?->isOnline(),
                'unread' => $c->unread,
                'last_message' => $c->latestMessage?->body
                    ?? ($c->latestMessage ? '['.$c->latestMessage->type.']' : null),
                'last_from_admin' => (bool) ($c->latestMessage?->is_from_admin),
                'updated_ago' => optional($c->updated_at)->diffForHumans(),
            ])->values()->all(),
            'meta' => $meta,
        ]);
    }

    /** Chat auto-reply settings (chat.manage), as on admin/messages/auto-reply. */
    public function autoReply(\App\Services\AutoReplyService $autoReply): JsonResponse
    {
        return response()->json([
            'enabled' => $autoReply->enabled(),
            'minutes' => $autoReply->minutes(),
            'message' => $autoReply->messageTemplate(),
        ]);
    }

    public function saveAutoReply(Request $request, \App\Services\AutoReplyService $autoReply): JsonResponse
    {
        $data = $request->validate(\App\Services\AutoReplyService::RULES + ['enabled' => ['required', 'boolean']]);

        $autoReply->save((bool) $data['enabled'], (int) $data['minutes'], $data['message']);

        return response()->json(['message' => 'Auto-reply settings saved.']);
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
            'time' => \App\Support\MessageTime::label($m->created_at),
        ];
    }
}
