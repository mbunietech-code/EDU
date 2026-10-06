<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    /**
     * The current user's support conversation with recent messages.
     */
    public function show(Request $request): JsonResponse
    {
        $conversation = Conversation::firstOrCreate(['user_id' => $request->user()->id]);

        app(\App\Services\AutoReplyService::class)->processConversation($conversation);

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

    /**
     * Send text, or an image / video / voice note (multipart "file" + "type"),
     * through the same ChatService as the web chat.
     */
    public function store(Request $request, ChatService $chat): JsonResponse
    {
        // Older app versions send only "body": treat that as a text message.
        $request->mergeIfMissing(['type' => 'text']);

        if ($request->input('type') === 'text') {
            $request->validate(['body' => ['required', 'string', 'max:4000']]);
        }

        $conversation = Conversation::firstOrCreate(['user_id' => $request->user()->id]);
        $message = $chat->send($conversation, $request, false);

        return response()->json(['data' => $this->row($message)]);
    }

    /** An attachment in the user's own support chat. */
    public function attachment(Request $request, ChatMessage $message)
    {
        $conversation = $message->conversation;
        abort_unless($conversation && (int) $conversation->user_id === (int) $request->user()->id, 404);
        abort_if(! $message->file_path, 404);
        abort_unless(Storage::disk('private')->exists($message->file_path), 404, 'This attachment is no longer available on the server.');

        return Storage::disk('private')->download($message->file_path, $message->file_name ?: basename($message->file_path));
    }

    private function row(ChatMessage $m): array
    {
        return [
            'id' => $m->id,
            'from_admin' => (bool) $m->is_from_admin,
            'type' => $m->type,
            'body' => $m->body,
            'has_file' => (bool) $m->file_path,
            'file_name' => $m->file_name,
            'created_at' => optional($m->created_at)->toIso8601String(),
            'time' => optional($m->created_at)->format('H:i'),
        ];
    }
}
