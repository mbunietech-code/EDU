<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin AI assistant for the app. Each admin only ever sees their own
 * conversations, exactly like the web page.
 */
class AiAssistantController extends Controller
{
    public function __construct(private AiAssistantService $assistant)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->allowed($request);
        $userId = $request->user()->id;

        return response()->json(['data' => [
            'conversations' => $this->assistant->listFor($userId)->map(fn (AiConversation $c) => [
                'id' => $c->id,
                'title' => $c->displayTitle(),
                'updated_ago' => $c->updated_at?->diffForHumans(),
            ])->values(),
            'quick_questions' => $this->assistant->quickQuestions(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allowed($request);

        $conversation = $this->assistant->startNew($request->user()->id);

        return response()->json(['data' => ['id' => $conversation->id, 'title' => $conversation->displayTitle(), 'messages' => []]], 201);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->own($request, $conversation);

        return response()->json(['data' => [
            'id' => $conversation->id,
            'title' => $conversation->displayTitle(),
            'messages' => $conversation->messages->map(fn (AiMessage $m) => $this->message($m))->values(),
        ]]);
    }

    public function send(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->own($request, $conversation);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'question_id' => ['nullable', 'string', 'max:50'],
        ]);

        $reply = $this->assistant->answer($conversation, $data['message'], $data['question_id'] ?? null);

        return response()->json(['data' => $this->message($reply) + ['conversation_title' => $conversation->refresh()->displayTitle()]]);
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->own($request, $conversation);
        $conversation->delete();

        return response()->json(['deleted' => true]);
    }

    protected function message(AiMessage $m): array
    {
        return [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'tools_used' => $m->tools_used,
            'time' => $m->created_at?->format('H:i'),
        ];
    }

    protected function allowed(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->hasPermission('ai.access'), 403, 'You do not have access to the AI assistant.');
    }

    protected function own(Request $request, AiConversation $conversation): void
    {
        $this->allowed($request);
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);
    }
}
