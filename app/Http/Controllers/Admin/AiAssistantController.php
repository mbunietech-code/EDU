<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AiAssistantService;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function __construct(private AiAssistantService $assistant)
    {
    }

    public function index(?AiConversation $conversation = null)
    {
        $userId = auth()->id();

        // A conversation id in the URL must belong to this admin — never
        // show another admin's AI Assistant history.
        if ($conversation && $conversation->user_id !== $userId) {
            abort(403);
        }

        $active = $conversation ?? $this->assistant->latestOrNew($userId);

        return view('admin.ai-assistant.index', [
            'conversations' => $this->assistant->listFor($userId),
            'active' => $active,
            'messages' => $active->messages,
            'quickQuestions' => $this->assistant->quickQuestions(),
        ]);
    }

    public function newConversation()
    {
        $conversation = $this->assistant->startNew(auth()->id());

        return redirect()->route('admin.ai-assistant.show', $conversation);
    }

    public function destroy(AiConversation $conversation)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        $conversation->delete();

        if (request()->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('admin.ai-assistant.index');
    }

    public function send(Request $request, AiConversation $conversation)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'question_id' => ['nullable', 'string', 'max:50'],
        ]);

        $reply = $this->assistant->answer($conversation, $data['message'], $data['question_id'] ?? null);

        return response()->json([
            'role' => $reply->role,
            'content' => $reply->content,
            'tools_used' => $reply->tools_used,
            'created_at' => $reply->created_at->toDateTimeString(),
            'conversation_title' => $conversation->refresh()->displayTitle(),
        ]);
    }
}
