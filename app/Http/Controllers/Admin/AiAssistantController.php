<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\DeepSeekClient;
use App\Services\AiAssistantService;
use App\Support\AiAssistantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin -> AI Assistant: a ChatGPT-style chat on DeepSeek. Sending is two
 * steps so the page can show the answer bit by bit without a WebSocket:
 * send() stores the message and an empty reply, run() generates it (a long
 * request), and the page polls poll() meanwhile. Every admin only ever sees
 * their own conversations.
 */
class AiAssistantController extends Controller
{
    public function __construct(private AiAssistantService $assistant, private AiAssistantSettings $settings)
    {
    }

    public function index(Request $request, ?AiConversation $conversation = null)
    {
        $userId = auth()->id();

        // A conversation id in the URL must belong to this admin: never
        // show another admin's AI Assistant history.
        if ($conversation && (int) $conversation->user_id !== (int) $userId) {
            abort(403);
        }

        $conversations = $this->assistant->listFor($userId);

        // Opening the page starts on an empty chat (reusing an empty one), like ChatGPT.
        $active = $conversation ?? $conversations->first(fn (AiConversation $c) => $c->title === null)
            ?? $this->assistant->startNew($userId);

        if (! $conversations->contains('id', $active->id)) {
            $conversations->prepend($active);
        }

        return view('admin.ai-assistant.index', [
            'conversations' => $conversations->filter(fn (AiConversation $c) => $c->title !== null || $c->id === $active->id)->values(),
            'active' => $active,
            'messages' => $active->messages->map(fn (AiMessage $m) => $m->toChatArray())->values(),
            'quickQuestions' => $this->assistant->quickQuestions(),
            'usesDeepSeek' => $this->assistant->usesDeepSeek(),
            'model' => $this->settings->model(),
            'ask' => Str::limit((string) $request->query('ask', ''), 500, ''),
        ]);
    }

    public function newConversation()
    {
        $conversation = AiConversation::forUser(auth()->id())->whereNull('title')->latest('id')->first()
            ?? $this->assistant->startNew(auth()->id());

        return redirect()->route('admin.ai-assistant.show', $conversation);
    }

    public function rename(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->own($conversation);

        $data = $request->validate(['title' => ['required', 'string', 'max:80']]);
        $conversation->update(['title' => trim($data['title'])]);

        return response()->json(['title' => $conversation->displayTitle()]);
    }

    public function destroy(AiConversation $conversation)
    {
        $this->own($conversation);

        $conversation->delete();

        if (request()->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('admin.ai-assistant.index');
    }

    /** Step 1: store the message and an empty reply; the page then calls run() and polls. */
    public function send(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->own($conversation);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:12000'],
            'question_id' => ['nullable', 'string', 'max:50'],
            'wait' => ['nullable', 'boolean'],
        ]);

        ['user' => $user, 'reply' => $reply] = $this->assistant->start($conversation, $data['message']);

        // Older callers (and quick questions) get the finished answer in the same request.
        if ($request->boolean('wait', true) || ! empty($data['question_id'])) {
            $reply = $this->assistant->run($reply, $request->user(), $data['question_id'] ?? null);
        }

        return response()->json($reply->toChatArray() + [
            'user_message' => $user->toChatArray(),
            'reply' => $reply->toChatArray(),
            'run_url' => route('admin.ai-assistant.messages.run', $reply),
            'poll_url' => route('admin.ai-assistant.messages.show', $reply),
            'conversation_title' => $conversation->refresh()->displayTitle(),
        ]);
    }

    public function regenerate(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->own($conversation);

        $reply = $this->assistant->regenerate($conversation);
        abort_if($reply === null, 422, 'Nothing to answer again.');

        return response()->json([
            'reply' => $reply->toChatArray(),
            'run_url' => route('admin.ai-assistant.messages.run', $reply),
            'poll_url' => route('admin.ai-assistant.messages.show', $reply),
        ]);
    }

    /** Step 2: generate the reply (long request; the page polls meanwhile). */
    public function run(Request $request, AiMessage $message): JsonResponse
    {
        $this->own($message->conversation);

        $reply = $this->assistant->run($message, $request->user());

        return response()->json($reply->toChatArray());
    }

    public function poll(AiMessage $message): JsonResponse
    {
        $this->own($message->conversation);

        return response()->json($message->toChatArray());
    }

    public function stop(AiMessage $message): JsonResponse
    {
        $this->own($message->conversation);

        $this->assistant->stop($message);

        return response()->json($message->refresh()->toChatArray());
    }

    // ---------------------------------------------------------------- settings

    public function settings()
    {
        return view('admin.ai-assistant.settings', [
            'settings' => $this->settings,
            'models' => AiAssistantSettings::MODELS,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:500'],
            'remove_key' => ['nullable', 'boolean'],
            'model' => ['required', Rule::in(array_keys(AiAssistantSettings::MODELS))],
            'daily_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'alerts_enabled' => ['nullable', 'boolean'],
            'payment_wait' => ['required', 'integer', 'min:5', 'max:1440'],
            'chat_wait' => ['required', 'integer', 'min:1', 'max:1440'],
            'instructions' => ['nullable', 'string', 'max:4000'],
        ]);

        $keyChange = null;
        if ($request->boolean('remove_key')) {
            $this->settings->setKey(null);
            $keyChange = 'removed';
        } elseif ($request->filled('key')) {
            $this->settings->setKey($data['key']);
            $keyChange = 'set';
        }

        $this->settings->set('ai_assistant_model', $data['model']);
        $this->settings->set('ai_assistant_daily_limit', (string) $data['daily_limit']);
        $this->settings->set('ai_assistant_alerts_enabled', $request->boolean('alerts_enabled') ? '1' : '0');
        $this->settings->set('ai_assistant_payment_wait', (string) $data['payment_wait']);
        $this->settings->set('ai_assistant_chat_wait', (string) $data['chat_wait']);
        $this->settings->set('ai_assistant_instructions', trim((string) ($data['instructions'] ?? '')));

        ActivityLog::log('ai_assistant_settings_updated', 'Setting', null, [
            'model' => $data['model'],
            'alerts' => $request->boolean('alerts_enabled'),
            'key' => $keyChange,
        ]);

        return back()->with('success', 'AI Assistant settings saved.');
    }

    public function testKey(DeepSeekClient $client)
    {
        $key = $this->settings->apiKey();
        if ($key === null) {
            return back()->with('error', 'No DeepSeek API key is saved.');
        }

        try {
            $reply = $client->ping($key, $this->settings->model());
        } catch (\Throwable $e) {
            return back()->with('error', 'DeepSeek test failed: '.Str::limit($e->getMessage(), 200));
        }

        return back()->with('success', 'DeepSeek works. Reply: '.Str::limit($reply, 160));
    }

    private function own(?AiConversation $conversation): void
    {
        abort_unless($conversation && (int) $conversation->user_id === (int) auth()->id(), 403);
    }
}
