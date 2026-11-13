<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\ChatbotService;
use App\Support\ChatbotSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** JSON endpoints behind the website chat widget (components/chatbot-widget). */
class ChatbotController extends Controller
{
    public function __construct(private ChatbotService $chatbot)
    {
    }

    public function history(Request $request): JsonResponse
    {
        $key = $request->session()->get('chatbot_key');

        return response()->json([
            'messages' => $key ? $this->chatbot->history($key) : [],
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:'.ChatbotSettings::MAX_MESSAGE_LENGTH],
        ]);

        $key = $request->session()->get('chatbot_key');
        if (! is_string($key) || $key === '') {
            $key = Str::random(40);
            $request->session()->put('chatbot_key', $key);
        }

        $result = $this->chatbot->reply($key, $validated['message'], $request->user()?->id, $request->ip());

        return response()->json($result + $this->support($request));
    }

    /** @return array{support_url:string,support_label:string} */
    private function support(Request $request): array
    {
        return $request->user()
            ? ['support_url' => route('user.chat.index'), 'support_label' => 'Ongea na msaada wetu']
            : ['support_url' => route('public.contact'), 'support_label' => 'Wasiliana na msaada'];
    }
}
