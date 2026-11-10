<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chatService)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = in_array($request->input('status'), ['unread', 'awaiting', 'replied', 'online'], true)
            ? $request->input('status') : 'all';
        $period = in_array($request->input('period'), ['today', '7d', '30d'], true)
            ? $request->input('period') : null;

        // Search + period narrow everything; the status tabs then split that set,
        // and each tab shows how many conversations it holds.
        $base = Conversation::query()
            ->whereHas('user', fn ($q) => $q->realUsers())
            ->when($search !== '', fn ($query) => $query->where(fn ($outer) => $outer
                ->whereHas('user', fn ($q) => $q->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->orWhereHas('messages', fn ($q) => $q->where('body', 'like', "%{$search}%"))
            ))
            ->when($period, fn ($query) => $query->whereHas('messages', fn ($q) => $q->where('created_at', '>=', match ($period) {
                'today' => now()->startOfDay(),
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
            })));

        $counts = [
            'all' => (clone $base)->count(),
            'unread' => $this->applyStatus(clone $base, 'unread')->count(),
            'awaiting' => $this->applyStatus(clone $base, 'awaiting')->count(),
            'replied' => $this->applyStatus(clone $base, 'replied')->count(),
            'online' => $this->applyStatus(clone $base, 'online')->count(),
        ];

        $conversations = $this->applyStatus(clone $base, $status)
            ->with(['user', 'latestMessage'])
            ->withCount(['messages as unread' => fn ($query) => $query->where('is_from_admin', false)->where('is_read', false)])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.chat.index', compact('conversations', 'search', 'status', 'period', 'counts'));
    }

    protected function applyStatus($query, string $status)
    {
        return match ($status) {
            'unread' => $query->whereHas('messages', fn ($q) => $q->where('is_from_admin', false)->where('is_read', false)),
            'awaiting' => $query->whereHas('latestMessage', fn ($q) => $q->where('is_from_admin', false)),
            'replied' => $query->whereHas('latestMessage', fn ($q) => $q->where('is_from_admin', true)),
            'online' => $query->whereHas('user', fn ($q) => $q->where('last_seen_at', '>', now()->subMinutes(2))),
            default => $query,
        };
    }

    public function create()
    {
        $users = User::query()
            ->realUsers()
            ->where('is_admin', false)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.chat.create', compact('users'));
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        abort_unless(User::query()->realUsers()->whereKey($validated['user_id'])->exists(), 404);

        $conversation = Conversation::firstOrCreate(['user_id' => $validated['user_id']]);

        return redirect()->route('admin.chat.show', $conversation);
    }

    public function show(Conversation $conversation)
    {
        $this->markUserMessagesRead($conversation);

        $messages = $conversation->messages()
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse();

        $payload = $messages->map(
            fn (ChatMessage $message) => $this->chatService->payload($conversation, $message, 'admin.chat.attachment', true)
        )->values();

        $customerOnline = $conversation->user->isOnline();

        return view('admin.chat.show', compact('conversation', 'messages', 'payload', 'customerOnline'));
    }

    public function store(Conversation $conversation, Request $request)
    {
        $message = $this->chatService->send($conversation, $request, true);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->chatService->payload($conversation, $message, 'admin.chat.attachment', true),
            ]);
        }

        return back();
    }

    public function fetch(Conversation $conversation, Request $request)
    {
        $after = max(0, (int) $request->input('after', 0));

        $messages = $conversation->messages()
            ->where('id', '>', $after)
            ->where('is_from_admin', false)
            ->orderBy('id')
            ->get();

        $this->markUserMessagesRead($conversation, $after);

        return response()->json([
            'messages' => $messages->map(
                fn (ChatMessage $message) => $this->chatService->payload($conversation, $message, 'admin.chat.attachment', true)
            ),
            'otherOnline' => $conversation->user->isOnline(),
        ]);
    }

    public function update(Conversation $conversation, ChatMessage $message, Request $request)
    {
        abort_unless($message->conversation_id === $conversation->id, 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        $this->chatService->edit($message, true, $data['body']);

        return response()->json([
            'message' => $this->chatService->payload($conversation, $message, 'admin.chat.attachment', true),
        ]);
    }

    public function destroy(Conversation $conversation, ChatMessage $message)
    {
        abort_unless($message->conversation_id === $conversation->id, 403);

        $this->chatService->delete($message, true);

        return response()->json([
            'message' => $this->chatService->payload($conversation, $message, 'admin.chat.attachment', true),
        ]);
    }

    public function attachment(Conversation $conversation, ChatMessage $message)
    {
        abort_unless($message->conversation_id === $conversation->id, 403);
        abort_if(! $message->file_path, 404);
        abort_unless(Storage::disk('private')->exists($message->file_path), 404, 'This attachment is no longer available on the server.');

        return Storage::disk('private')->download($message->file_path, $message->file_name ?: basename($message->file_path));
    }

    protected function markUserMessagesRead(Conversation $conversation, int $after = 0): void
    {
        $conversation->messages()
            ->where('id', '>', $after)
            ->where('is_from_admin', false)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }
}
