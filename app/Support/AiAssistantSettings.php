<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\CredentialService;
use Illuminate\Support\Collection;

/**
 * Admin AI Assistant settings, stored in the `settings` table (group
 * "ai_assistant"). The DeepSeek API key is encrypted with APP_KEY and only
 * ever used on the server. When no key of its own is saved, the website
 * chatbot's DeepSeek key is used, so one key can serve both.
 */
class AiAssistantSettings
{
    public const GROUP = 'ai_assistant';

    public const MODELS = [
        'deepseek-chat' => 'DeepSeek Chat (fast, everyday)',
        'deepseek-reasoner' => 'DeepSeek Reasoner (thinks longer, no data tools)',
    ];

    public const DEFAULT_MODEL = 'deepseek-chat';

    public const DEFAULT_DAILY_LIMIT = 300;

    public const DEFAULT_PAYMENT_WAIT_MINUTES = 20;

    public const DEFAULT_CHAT_WAIT_MINUTES = 5;

    private ?Collection $cache = null;

    public function __construct(private CredentialService $credentials, private ChatbotSettings $chatbot)
    {
    }

    private function values(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            return $this->cache = Setting::where('group', self::GROUP)->pluck('value', 'key');
        } catch (\Throwable) {
            return collect();
        }
    }

    public function ownKey(): ?string
    {
        $encrypted = $this->values()->get('ai_assistant_key_deepseek');
        if (! $encrypted) {
            return null;
        }

        $key = $this->credentials->decryptValue($encrypted);

        return $key !== null && $key !== '' ? $key : null;
    }

    /** The key to call DeepSeek with: our own, else the website chatbot's DeepSeek key. */
    public function apiKey(): ?string
    {
        return $this->ownKey() ?? $this->chatbot->apiKey('deepseek');
    }

    public function hasKey(): bool
    {
        return $this->apiKey() !== null;
    }

    /** 'own' | 'chatbot' | null */
    public function keySource(): ?string
    {
        if ($this->ownKey() !== null) {
            return 'own';
        }

        return $this->chatbot->apiKey('deepseek') !== null ? 'chatbot' : null;
    }

    public function maskedKey(): ?string
    {
        $key = $this->apiKey();

        return $key === null ? null : '••••'.substr($key, -4);
    }

    public function setKey(?string $key): void
    {
        $key = trim((string) $key);
        Setting::set('ai_assistant_key_deepseek', $key === '' ? '' : $this->credentials->encrypt($key), 'encrypted', self::GROUP);
        $this->cache = null;
    }

    public function model(): string
    {
        $model = (string) $this->values()->get('ai_assistant_model', self::DEFAULT_MODEL);

        return array_key_exists($model, self::MODELS) ? $model : self::DEFAULT_MODEL;
    }

    public function dailyLimit(): int
    {
        return max(0, (int) $this->values()->get('ai_assistant_daily_limit', self::DEFAULT_DAILY_LIMIT));
    }

    public function alertsEnabled(): bool
    {
        return (string) $this->values()->get('ai_assistant_alerts_enabled', '1') === '1';
    }

    public function paymentWaitMinutes(): int
    {
        return max(5, (int) $this->values()->get('ai_assistant_payment_wait', self::DEFAULT_PAYMENT_WAIT_MINUTES));
    }

    public function chatWaitMinutes(): int
    {
        return max(1, (int) $this->values()->get('ai_assistant_chat_wait', self::DEFAULT_CHAT_WAIT_MINUTES));
    }

    public function watchStartedAt(): ?\Illuminate\Support\Carbon
    {
        $value = (string) $this->values()->get('ai_assistant_watch_since', '');

        return $value !== '' ? \Illuminate\Support\Carbon::parse($value) : null;
    }

    /** Extra house rules the admin adds to the built-in instructions. */
    public function extraInstructions(): string
    {
        return trim((string) $this->values()->get('ai_assistant_instructions', ''));
    }

    public function set(string $key, string $value): void
    {
        Setting::set($key, $value, 'string', self::GROUP);
        $this->cache = null;
    }
}
