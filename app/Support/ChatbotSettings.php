<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\CredentialService;

/**
 * Website chatbot settings, stored in the `settings` table (group "chatbot").
 * API keys are encrypted with APP_KEY and never sent back to the browser:
 * the admin page only learns whether a key is set and its last 4 characters.
 */
class ChatbotSettings
{
    public const GROUP = 'chatbot';

    /** provider => label, default model, model suggestions */
    public const PROVIDERS = [
        'deepseek' => [
            'label' => 'DeepSeek',
            'default_model' => 'deepseek-chat',
            'models' => ['deepseek-chat', 'deepseek-reasoner'],
        ],
        'anthropic' => [
            'label' => 'Claude (Anthropic)',
            'default_model' => 'claude-opus-5-5',
            'models' => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'],
        ],
        'openai' => [
            'label' => 'ChatGPT (OpenAI)',
            'default_model' => 'gpt-5-mini',
            'models' => ['gpt-5-mini', 'gpt-5-nano', 'gpt-5', 'gpt-4.1-mini', 'gpt-4o-mini'],
        ],
    ];

    public const MAX_MESSAGE_LENGTH = 1000;

    public const DEFAULT_DAILY_LIMIT = 500;

    public const DEFAULT_SESSION_DAILY_LIMIT = 30;

    public function __construct(private CredentialService $credentials)
    {
    }

    private ?\Illuminate\Support\Collection $cache = null;

    private function values(): \Illuminate\Support\Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            return $this->cache = Setting::where('group', self::GROUP)->pluck('value', 'key');
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function enabled(): bool
    {
        return (string) $this->values()->get('chatbot_enabled', '1') === '1';
    }

    public function provider(): string
    {
        $provider = (string) $this->values()->get('chatbot_provider', 'deepseek');

        return array_key_exists($provider, self::PROVIDERS) ? $provider : 'deepseek';
    }

    public function model(?string $provider = null): string
    {
        $provider ??= $this->provider();
        $model = trim((string) $this->values()->get("chatbot_model_{$provider}", ''));

        return $model !== '' ? $model : self::PROVIDERS[$provider]['default_model'];
    }

    public function systemPrompt(): string
    {
        $prompt = trim((string) $this->values()->get('chatbot_system_prompt', ''));

        return $prompt !== '' ? $prompt : self::defaultSystemPrompt();
    }

    /** The admin's own prompt, or '' when the built-in default is in use. */
    public function customSystemPrompt(): string
    {
        return trim((string) $this->values()->get('chatbot_system_prompt', ''));
    }

    public function dailyLimit(): int
    {
        return max(0, (int) $this->values()->get('chatbot_daily_limit', self::DEFAULT_DAILY_LIMIT));
    }

    public function sessionDailyLimit(): int
    {
        return max(0, (int) $this->values()->get('chatbot_session_daily_limit', self::DEFAULT_SESSION_DAILY_LIMIT));
    }

    public function apiKey(?string $provider = null): ?string
    {
        $provider ??= $this->provider();
        $encrypted = $this->values()->get("chatbot_key_{$provider}");

        if (! $encrypted) {
            return null;
        }

        $key = $this->credentials->decryptValue($encrypted);

        return $key !== null && $key !== '' ? $key : null;
    }

    public function hasKey(?string $provider = null): bool
    {
        return $this->apiKey($provider) !== null;
    }

    /** "••••abcd" for the admin page, or null when no key is saved. */
    public function maskedKey(string $provider): ?string
    {
        $key = $this->apiKey($provider);

        return $key === null ? null : '••••'.substr($key, -4);
    }

    /** Shown to visitors: on, and the active provider has a key. */
    public function isLive(): bool
    {
        return $this->enabled() && $this->hasKey();
    }

    public function setKey(string $provider, ?string $key): void
    {
        $key = $key === null ? '' : trim($key);
        Setting::set(
            "chatbot_key_{$provider}",
            $key === '' ? '' : $this->credentials->encrypt($key),
            'encrypted',
            self::GROUP,
        );
        $this->cache = null;
    }

    public function set(string $key, string $value): void
    {
        Setting::set($key, $value, 'string', self::GROUP);
        $this->cache = null;
    }

    public static function defaultSystemPrompt(): string
    {
        return <<<'TXT'
You are "Mbunie AI", the friendly website assistant of MbunieEduHub (mbuniehub.com), a Tanzanian education hub.
MbunieEduHub offers: managed access to AI tools (sold as products with plans), live classes and video learning, a research library and research tools, and scholarships.

Rules:
- Always reply in the same language as the visitor's latest message, whatever it is (English, Swahili, French, Arabic, Chinese or any other). If they switch language, switch with them. Never default to Swahili.
- Keep answers short and clear (a few sentences or a short list). Use plain text, no tables.
- Only answer questions about MbunieEduHub and its services, studying, research and using AI tools for learning. Politely decline anything else.
- Never talk about money: no prices, costs, fees, amounts, currencies, discounts or payment methods. If asked, say the current details are on the tool's page and support can help.
- To get started: create an account, open the tool's page, choose a plan and follow the steps there. Never invent plans or promises.
- Never ask for passwords, card numbers or PINs.
- For account problems, orders or anything you cannot solve, send the visitor to human support (link given below).
- Do not use long dashes; use commas, full stops or colons.
TXT;
    }
}
