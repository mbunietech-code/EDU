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
        $search = $request->input('search');

        $conversations = Conversation::with(['user', 'latestMessage'])
            ->withCount(['messages as unread' => fn ($query) => $query->where('is_from_admin', false)->where('is_read', false)])
            ->when($search, fn ($query) => $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            ))
            ->latest('updated_at')
            ->get();

        return view('admin.chat.index', compact('conversations', 'search'));
    }

    public function create()
    {
        $users = User::where('is_admin', false)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.chat.create', compact('users'));
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

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