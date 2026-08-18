<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chatService)
    {
    }

    public function index()
    {
        $conversations = Conversation::with(['user', 'latestMessage'])
            ->withCount(['messages as unread' => fn ($query) => $query->where('is_from_admin', false)->where('is_read', false)])
            ->latest('updated_at')
            ->get();

        return view('admin.chat.index', compact('conversations'));
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
            fn (ChatMessage $message) => $this->chatService->payload($conversation, $message, 'admin.chat.attachment')
        )->values();

        return view('admin.chat.show', compact('conversation', 'messages', 'payload'));
    }

    public function store(Conversation $conversation, Request $request)
    {
        $message = $this->chatService->send($conversation, $request, true);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->chatService->payload($conversation, $message, 'admin.chat.attachment'),
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
                fn (ChatMessage $message) => $this->chatService->payload($conversation, $message, 'admin.chat.attachment')
            ),
        ]);
    }

    public function attachment(Conversation $conversation, ChatMessage $message)
    {
        abort_unless($message->conversation_id === $conversation->id, 403);
        abort_if(! $message->file_path, 404);

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