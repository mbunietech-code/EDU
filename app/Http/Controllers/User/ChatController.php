<?php

namespace App\Http\Controllers\User;

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

    public function index()
    {
        $conversation = Conversation::firstOrCreate(['user_id' => auth()->id()]);

        return redirect()->route('user.chat.show', $conversation);
    }

    public function show(Conversation $conversation)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        $this->markAdminMessagesRead($conversation);

        $messages = $conversation->messages()
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse();

        $payload = $messages->map(
            fn (ChatMessage $message) => $this->chatService->payload($conversation, $message, 'user.chat.attachment')
        )->values();

        $supportOnline = User::anyAdminOnline();

        return view('user.chat.show', compact('conversation', 'messages', 'payload', 'supportOnline'));
    }

    public function store(Conversation $conversation, Request $request)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        $message = $this->chatService->send($conversation, $request, false);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->chatService->payload($conversation, $message, 'user.chat.attachment'),
            ]);
        }

        return back();
    }

    public function fetch(Conversation $conversation, Request $request)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        $after = max(0, (int) $request->input('after', 0));

        $messages = $conversation->messages()
            ->where('id', '>', $after)
            ->where('is_from_admin', true)
            ->orderBy('id')
            ->get();

        $this->markAdminMessagesRead($conversation, $after);

        return response()->json([
            'messages' => $messages->map(
                fn (ChatMessage $message) => $this->chatService->payload($conversation, $message, 'user.chat.attachment')
            ),
            'otherOnline' => User::anyAdminOnline(),
        ]);
    }

    public function attachment(Conversation $conversation, ChatMessage $message)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);
        abort_unless($message->conversation_id === $conversation->id, 403);
        abort_if(! $message->file_path, 404);
        abort_unless(Storage::disk('private')->exists($message->file_path), 404, 'This attachment is no longer available on the server.');

        return Storage::disk('private')->download($message->file_path, $message->file_name ?: basename($message->file_path));
    }

    protected function markAdminMessagesRead(Conversation $conversation, int $after = 0): void
    {
        $conversation->messages()
            ->where('id', '>', $after)
            ->where('is_from_admin', true)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }
}