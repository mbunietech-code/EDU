<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotMessage;
use App\Models\LearningCourse;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Scholarship;
use App\Support\ChatbotSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Website chatbot for visitors and customers on the public pages. Calls the
 * provider the admin picked (DeepSeek, Claude or ChatGPT) from PHP only, so
 * API keys never reach the browser. Every exchange is stored in
 * chatbot_messages: that is the bot's short-term memory and what the daily
 * caps count. Any failure (no key, provider down, cap reached) returns a
 * friendly message that points to human support instead of an error.
 */
class ChatbotService
{
    private const HISTORY_MESSAGES = 10;

    private const TIMEOUT_SECONDS = 25;

    public function __construct(private ChatbotSettings $settings)
    {
    }

    /** @return list<array{role:string,content:string}> */
    public function history(string $sessionKey): array
    {
        return ChatbotMessage::where('session_key', $sessionKey)
            ->orderByDesc('id')
            ->limit(30)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    /**
     * @return array{reply:string,status:string}
     */
    public function reply(string $sessionKey, string $message, ?int $userId = null, ?string $ip = null): array
    {
        $message = trim(Str::limit($message, ChatbotSettings::MAX_MESSAGE_LENGTH, ''));
        $provider = $this->settings->provider();
        $model = $this->settings->model($provider);

        $question = ChatbotMessage::create([
            'session_key' => $sessionKey,
            'user_id' => $userId,
            'role' => 'user',
            'content' => $message,
            'ip' => $ip,
        ]);

        [$reply, $status] = $this->answer($sessionKey, $message, $provider, $model, $question->id);

        $question->update(['status' => $status]);
        ChatbotMessage::create([
            'session_key' => $sessionKey,
            'user_id' => $userId,
            'role' => 'assistant',
            'content' => $reply,
            'provider' => $status === 'ok' ? $provider : null,
            'model' => $status === 'ok' ? $model : null,
            'status' => $status,
            'ip' => $ip,
        ]);

        return ['reply' => $reply, 'status' => $status];
    }

    /** @return array{0:string,1:string} [reply, status] */
    private function answer(string $sessionKey, string $message, string $provider, string $model, int $questionId): array
    {
        if (! $this->settings->enabled()) {
            return [$this->fallbackText(), 'disabled'];
        }

        $key = $this->settings->apiKey($provider);
        if ($key === null) {
            return [$this->fallbackText(), 'no_key'];
        }

        if ($this->overLimit($sessionKey)) {
            return [$this->limitText(), 'limit'];
        }

        $messages = $this->conversation($sessionKey, $questionId);
        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $text = $this->callProvider($provider, $model, $key, $this->fullSystemPrompt(), $messages);
        } catch (\Throwable $e) {
            Log::warning('Chatbot provider call failed', [
                'provider' => $provider,
                'model' => $model,
                'error' => Str::limit($e->getMessage(), 300),
            ]);

            return [$this->fallbackText(), 'error'];
        }

        $text = trim((string) $text);

        return $text === '' ? [$this->fallbackText(), 'error'] : [$this->cleanDashes($text), 'ok'];
    }

    /**
     * Send one short test message with the given provider/key (admin "Test" button).
     *
     * @return array{ok:bool,message:string}
     */
    public function test(string $provider, ?string $model = null): array
    {
        $key = $this->settings->apiKey($provider);
        if ($key === null) {
            return ['ok' => false, 'message' => 'No API key saved for '.ChatbotSettings::PROVIDERS[$provider]['label'].'.'];
        }

        $model ??= $this->settings->model($provider);

        try {
            $text = $this->callProvider($provider, $model, $key, 'You are a test. Reply with one short sentence.', [
                ['role' => 'user', 'content' => 'Say hello in Swahili.'],
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => Str::limit($e->getMessage(), 300)];
        }

        return ['ok' => true, 'message' => Str::limit(trim((string) $text), 200)];
    }

    /** Earlier answered turns of this conversation, oldest first. */
    private function conversation(string $sessionKey, int $beforeId): array
    {
        return ChatbotMessage::where('session_key', $sessionKey)
            ->where('id', '<', $beforeId)
            ->where('status', 'ok')
            ->orderByDesc('id')
            ->limit(self::HISTORY_MESSAGES)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    private function overLimit(string $sessionKey): bool
    {
        $today = now()->startOfDay();
        $base = ChatbotMessage::where('role', 'user')->where('created_at', '>=', $today)->where('status', 'ok');

        $daily = $this->settings->dailyLimit();
        if ($daily > 0 && (clone $base)->count() >= $daily) {
            return true;
        }

        $perSession = $this->settings->sessionDailyLimit();

        return $perSession > 0 && (clone $base)->where('session_key', $sessionKey)->count() >= $perSession;
    }

    /**
     * @param  list<array{role:string,content:string}>  $messages
     */
    private function callProvider(string $provider, string $model, string $key, string $system, array $messages): string
    {
        return match ($provider) {
            'anthropic' => $this->callAnthropic($model, $key, $system, $messages),
            'openai' => $this->callOpenAiCompatible('https://api.openai.com/v1/chat/completions', $model, $key, $system, $messages, [
                'max_completion_tokens' => 2000,
            ]),
            default => $this->callOpenAiCompatible('https://api.deepseek.com/chat/completions', $model, $key, $system, $messages, [
                'max_tokens' => 800,
            ]),
        };
    }

    private function callOpenAiCompatible(string $url, string $model, string $key, string $system, array $messages, array $extra): string
    {
        $response = Http::withToken($key)
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(10)
            ->post($url, array_merge([
                'model' => $model,
                'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
            ], $extra));

        if ($response->failed()) {
            throw new \RuntimeException('HTTP '.$response->status().': '.Str::limit((string) ($response->json('error.message') ?? $response->body()), 200));
        }

        return (string) $response->json('choices.0.message.content', '');
    }

    private function callAnthropic(string $model, string $key, string $system, array $messages): string
    {
        $headers = [
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
        ];

        $body = [
            'model' => $model,
            'max_tokens' => 4096,
            'system' => $system,
            'messages' => $messages,
        ];

        // Current models think by default; a website FAQ needs little of it.
        if (preg_match('/^claude-(opus-5|sonnet-5|haiku-5|fable-5|opus-4-[678])/', $model)) {
            $body['output_config'] = ['effort' => 'low'];
        }

        // On a safety decline, let the API retry on a fallback model in the same call.
        if (preg_match('/^claude-(fable-5-[12]|opus-5-5|opus-5|sonnet-5-5)$/', $model)) {
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
            $body['fallbacks'] = 'default';
        }

        $response = Http::withHeaders($headers)
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(10)
            ->post('https://api.anthropic.com/v1/messages', $body);

        if ($response->failed()) {
            throw new \RuntimeException('HTTP '.$response->status().': '.Str::limit((string) ($response->json('error.message') ?? $response->body()), 200));
        }

        if ($response->json('stop_reason') === 'refusal') {
            throw new \RuntimeException('Request declined by the model.');
        }

        return collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }

    private function fullSystemPrompt(): string
    {
        $support = route('public.contact');
        $chat = route('user.chat.index');

        return $this->settings->systemPrompt()
            ."\n\nToday (Tanzania time): ".now('Africa/Dar_es_Salaam')->format('l, j F Y')
            ."\nWebsite: ".url('/')
            ."\nHuman support: signed-in customers chat with our team at {$chat}; visitors can use the contact page {$support}."
            ."\n\nSite facts (live data):\n".$this->siteFacts()
            // Last on purpose, so it holds even with an admin's own prompt and the
            // Tanzanian site facts above: answer in the language the visitor used.
            ."\n\nLanguage: reply in the same language as the visitor's latest message (any language). Do not switch to Swahili unless the visitor wrote in Swahili.";
    }

    /** Short, cached summary of what the site sells, so the bot quotes real prices. */
    public function siteFacts(): string
    {
        return Cache::remember('chatbot:site-facts', now()->addMinutes(10), function () {
            $lines = [];

            try {
                $products = Product::published()
                    ->with(['plans' => fn ($q) => $q->active()->orderBy('sort_order')])
                    ->orderBy('name')
                    ->limit(40)
                    ->get();

                if ($products->isNotEmpty()) {
                    $lines[] = 'AI tools and plans (prices in TZS):';
                    foreach ($products as $product) {
                        $plans = $product->plans->map(fn ($p) => $p->name.' ('.$p->durationLabel().'): TZS '.number_format((float) $p->price))->implode('; ');
                        $lines[] = '- '.$product->name.($plans !== '' ? ': '.$plans : '').' | '.route('public.products.show', $product);
                    }
                }
            } catch (\Throwable $e) {
                // Catalogue unavailable: the bot will send visitors to the tools page instead.
            }

            $lines[] = 'All tools: '.route('public.products.index');

            try {
                $methods = PaymentMethod::enabled()->orderBy('sort_order')->pluck('name')->filter()->implode(', ');
                if ($methods !== '') {
                    $lines[] = 'Payment methods: '.$methods.'.';
                }
            } catch (\Throwable $e) {
            }

            try {
                $scholarships = Scholarship::published()->open()->latest('id')->limit(5)->pluck('title')->implode('; ');
                $lines[] = 'Scholarships: '.route('public.scholarships.index').($scholarships !== '' ? ' (open now: '.$scholarships.')' : '');
            } catch (\Throwable $e) {
            }

            try {
                $courses = LearningCourse::published()->count();
                $lines[] = 'Learning: '.$courses.' published courses plus live classes, for signed-in members.';
            } catch (\Throwable $e) {
            }

            $lines[] = 'Research library and research tools: '.route('research.index');
            $lines[] = 'FAQ: '.route('public.faq');

            return implode("\n", $lines);
        });
    }

    private function fallbackText(): string
    {
        return "Sorry, I can't answer right now. Please contact our support team.\nSamahani, siwezi kujibu kwa sasa. Tafadhali wasiliana na timu yetu ya msaada.";
    }

    private function limitText(): string
    {
        return "You have reached today's question limit. Please contact our support team.\nUmefikia kikomo cha maswali kwa leo. Tafadhali wasiliana na timu yetu ya msaada.";
    }

    /** Site rule: no long dashes in visitor-facing text. */
    private function cleanDashes(string $text): string
    {
        return str_replace([' — ', '—', ' – ', '–'], [', ', ', ', ', ', '-'], $text);
    }
}
