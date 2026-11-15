<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\DeepSeekClient;
use App\Support\AiAssistantSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The admin "AI Assistant": a ChatGPT-style chat powered by DeepSeek. Each
 * admin has many saved conversations; the recent turns go to DeepSeek as
 * context together with read-only data tools (AiAssistantTools) limited to
 * what this admin may see. A reply is written to the database while it is
 * generated (status "streaming") so the page can poll it and show the text
 * bit by bit (no WebSocket on shared hosting). Without a DeepSeek key, or
 * past the daily cap, the free rule-based answers (quick questions and
 * keywords) are used instead.
 */
class AiAssistantService
{
    private const HISTORY_MESSAGES = 24;

    private const MAX_TOOL_ROUNDS = 6;

    private const FLUSH_SECONDS = 0.35;

    public function __construct(
        private AiAssistantTools $tools,
        private AiAssistantSettings $settings,
        private DeepSeekClient $client,
    ) {
    }

    public function usesDeepSeek(): bool
    {
        return $this->settings->hasKey();
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    public function quickQuestions(): array
    {
        return [
            ['id' => 'optimization', 'label' => 'Database optimization summary'],
            ['id' => 'orders', 'label' => 'Orders summary'],
            ['id' => 'payments', 'label' => 'Payments summary'],
            ['id' => 'users', 'label' => 'Users summary'],
            ['id' => 'accounts', 'label' => 'Accounts inventory'],
            ['id' => 'subscriptions', 'label' => 'Subscriptions summary'],
            ['id' => 'errors', 'label' => 'Error logs summary'],
            ['id' => 'contact', 'label' => 'Contact messages'],
            ['id' => 'health', 'label' => 'Database health'],
        ];
    }

    /**
     * All of this admin's past conversations, most recently active first —
     * the "history" list.
     *
     * @return Collection<int,AiConversation>
     */
    public function listFor(int $userId): Collection
    {
        return AiConversation::forUser($userId)->latest('updated_at')->get();
    }

    /**
     * Fetch one of this admin's conversations by id — never another
     * admin's, even another super admin's own AI Assistant history.
     */
    public function find(int $userId, int $conversationId): ?AiConversation
    {
        return AiConversation::forUser($userId)->find($conversationId);
    }

    public function startNew(int $userId): AiConversation
    {
        return AiConversation::create(['user_id' => $userId]);
    }

    /**
     * The conversation to show when none was explicitly selected: the most
     * recently active one, or a fresh empty one if this admin has none yet.
     */
    public function latestOrNew(int $userId): AiConversation
    {
        return AiConversation::forUser($userId)->latest('updated_at')->first()
            ?? $this->startNew($userId);
    }

    /**
     * Answer one message in a single call (the MHub app API): store the
     * admin's message, generate the reply, return it.
     */
    public function answer(AiConversation $conversation, string $input, ?string $questionId = null, ?User $admin = null): AiMessage
    {
        ['reply' => $reply] = $this->start($conversation, $input);

        return $this->run($reply, $admin ?? $conversation->user, $questionId);
    }

    /**
     * Store the admin's message and an empty reply the page can poll.
     *
     * @return array{user:AiMessage,reply:AiMessage}
     */
    public function start(AiConversation $conversation, string $input): array
    {
        $user = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $input,
        ]);

        if ($conversation->title === null) {
            $conversation->update(['title' => Str::limit(preg_replace('/\s+/', ' ', $input), 40)]);
        } else {
            $conversation->touch();
        }

        return ['user' => $user, 'reply' => $this->placeholder($conversation)];
    }

    /** Answer the last message again ("Regenerate"). */
    public function regenerate(AiConversation $conversation): ?AiMessage
    {
        $last = $conversation->messages()->reorder()->latest('id')->first();
        if ($last && $last->role === 'assistant') {
            if ($last->status === AiMessage::STREAMING) {
                return null;
            }
            $last->delete();
            $last = $conversation->messages()->reorder()->latest('id')->first();
        }
        if (! $last || $last->role !== 'user') {
            return null;
        }

        $conversation->touch();

        return $this->placeholder($conversation);
    }

    /** The reply is cut off at its next write (the "Stop" button). */
    public function stop(AiMessage $reply): void
    {
        AiMessage::whereKey($reply->id)->where('status', AiMessage::STREAMING)->update(['status' => AiMessage::STOPPED]);
    }

    private function placeholder(AiConversation $conversation): AiMessage
    {
        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => '',
            'status' => AiMessage::STREAMING,
        ]);
    }

    /**
     * Generate the reply for $reply (an empty "streaming" message), writing
     * the text as it arrives. Safe to call once per reply; a second call on
     * a finished reply just returns it.
     */
    public function run(AiMessage $reply, ?User $admin, ?string $questionId = null): AiMessage
    {
        if ($reply->status !== AiMessage::STREAMING) {
            return $reply;
        }

        $question = AiMessage::where('conversation_id', $reply->conversation_id)
            ->where('id', '<', $reply->id)->where('role', 'user')->latest('id')->first();
        $input = (string) $question?->content;

        if (! $this->usesDeepSeek() || $questionId !== null || $this->overDailyLimit($admin)) {
            return $this->ruleBased($reply, $input, $questionId);
        }

        // One generation per reply, even if run is called twice (double click, two tabs).
        $lock = Cache::lock('ai-assistant-run-'.$reply->id, 200);
        if (! $lock->get()) {
            return $reply;
        }

        // Web only: the answer keeps being written even if the tab closes. (In the
        // console a time limit would apply to the whole process, e.g. a test run.)
        if (! app()->runningInConsole()) {
            @set_time_limit(180);
            ignore_user_abort(true);
        }

        try {
            return $this->generate($reply, $admin);
        } catch (Throwable $e) {
            Log::warning('AI Assistant DeepSeek call failed', ['error' => Str::limit($e->getMessage(), 300)]);
            $reply->refresh();
            $text = trim((string) $reply->content);
            $reply->update([
                'content' => ($text !== '' ? $text."\n\n" : '').'_DeepSeek could not answer right now ('.Str::limit($e->getMessage(), 160).'). Try again in a moment._',
                'status' => AiMessage::ERROR,
                'meta' => ['error' => Str::limit($e->getMessage(), 300)],
            ]);

            return $reply;
        } finally {
            $lock->release();
        }
    }

    private function ruleBased(AiMessage $reply, string $input, ?string $questionId): AiMessage
    {
        $id = $questionId ?? $this->resolveQuestionId($input);

        [$content, $toolsUsed] = $id !== null
            ? $this->buildAnswer($id)
            : [$this->helpText(), null];

        $reply->update([
            'content' => $content,
            'tools_used' => $toolsUsed,
            'status' => AiMessage::DONE,
            'meta' => ['provider' => 'rules'],
        ]);

        return $reply;
    }

    private function overDailyLimit(?User $admin): bool
    {
        $limit = $this->settings->dailyLimit();
        if ($limit === 0 || ! $admin) {
            return false;
        }

        $today = AiMessage::query()
            ->whereIn('conversation_id', AiConversation::forUser($admin->id)->select('id'))
            ->where('role', 'user')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return $today > $limit;
    }

    private function generate(AiMessage $reply, ?User $admin): AiMessage
    {
        $key = (string) $this->settings->apiKey();
        $model = $this->settings->model();
        $tools = $model === 'deepseek-reasoner' ? [] : $this->tools->definitions($admin);

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($admin, $tools !== [])]],
            $this->history($reply),
        );

        $content = '';
        $toolsUsed = [];
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0];
        $lastFlush = microtime(true);
        $stopped = false;

        $flush = function (bool $force = false, ?string $activity = null) use (&$content, &$lastFlush, &$stopped, &$toolsUsed, $reply): bool {
            if (! $force && microtime(true) - $lastFlush < self::FLUSH_SECONDS) {
                return true;
            }
            $lastFlush = microtime(true);

            // Saving only while still streaming: a Stop click wins.
            $saved = AiMessage::whereKey($reply->id)->where('status', AiMessage::STREAMING)->update([
                'content' => $content,
                'tools_used' => $toolsUsed === [] ? null : json_encode(array_values(array_unique($toolsUsed))),
                'meta' => $activity !== null ? json_encode(['activity' => $activity]) : null,
                'updated_at' => now(),
            ]);
            if ($saved === 0) {
                $stopped = true;
            }

            return ! $stopped;
        };

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS && ! $stopped; $round++) {
            $lastRound = $round === self::MAX_TOOL_ROUNDS - 1;
            $prefix = $content;

            $result = $this->client->stream($key, $model, $messages, $lastRound ? [] : $tools, function (string $piece) use (&$content, $flush) {
                $content .= $piece;

                return $flush();
            });

            foreach (['prompt_tokens', 'completion_tokens'] as $k) {
                $usage[$k] += (int) ($result['usage'][$k] ?? 0);
            }

            if ($result['stopped'] || $stopped || $result['tool_calls'] === []) {
                break;
            }

            // The model asked for data: run the tools and give it the results.
            $roundText = substr($content, strlen($prefix));
            $messages[] = [
                'role' => 'assistant',
                'content' => $roundText !== '' ? $roundText : null,
                'tool_calls' => array_map(fn ($c) => [
                    'id' => $c['id'],
                    'type' => 'function',
                    'function' => ['name' => $c['name'], 'arguments' => $c['arguments'] !== '' ? $c['arguments'] : '{}'],
                ], $result['tool_calls']),
            ];

            foreach ($result['tool_calls'] as $call) {
                $toolsUsed[] = $call['name'];
                $flush(true, $this->activityLabel($call['name']));
                $args = json_decode($call['arguments'] ?: '{}', true);
                $data = $this->tools->call($call['name'], is_array($args) ? $args : [], $admin);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'],
                    'content' => Str::limit(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), 14000),
                ];
            }

            if ($content !== '' && ! str_ends_with($content, "\n")) {
                $content .= "\n\n";
            }
        }

        $reply->refresh();
        $wasStopped = $reply->status === AiMessage::STOPPED;
        $final = $this->tidy($content);

        $reply->forceFill([
            'content' => $final !== '' ? $final : ($wasStopped ? '_Stopped._' : '_No answer came back. Try again._'),
            'tools_used' => $toolsUsed === [] ? null : array_values(array_unique($toolsUsed)),
            'status' => $wasStopped ? AiMessage::STOPPED : AiMessage::DONE,
            'meta' => ['provider' => 'deepseek', 'model' => $model, 'usage' => $usage],
        ])->save();

        return $reply;
    }

    /** Recent turns of this conversation before $reply, oldest first. */
    private function history(AiMessage $reply): array
    {
        return AiMessage::where('conversation_id', $reply->conversation_id)
            ->where('id', '<', $reply->id)
            ->orderByDesc('id')
            ->limit(self::HISTORY_MESSAGES)
            ->get(['role', 'content', 'status'])
            ->reverse()
            ->filter(fn (AiMessage $m) => trim((string) $m->content) !== '' && $m->status !== AiMessage::ERROR)
            ->map(fn (AiMessage $m) => [
                'role' => $m->role === 'user' ? 'user' : 'assistant',
                'content' => Str::limit((string) $m->content, 8000),
            ])
            // Two turns in a row from the same side (after a failed reply) are joined.
            ->reduce(function (array $carry, array $m) {
                $last = count($carry) - 1;
                if ($last >= 0 && $carry[$last]['role'] === $m['role']) {
                    $carry[$last]['content'] .= "\n\n".$m['content'];
                } else {
                    $carry[] = $m;
                }

                return $carry;
            }, []);
    }

    private function activityLabel(string $tool): string
    {
        return 'Reading '.str_replace('_', ' ', preg_replace('/^(get|list|find)_/', '', $tool)).'...';
    }

    /** Site rule: no long dashes in text we may publish. Code blocks are left alone. */
    private function tidy(string $text): string
    {
        $parts = preg_split('/(```.*?```)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        foreach ($parts as $i => $part) {
            if (! str_starts_with($part, '```')) {
                $parts[$i] = str_replace([' — ', ' – ', '—', '–'], [', ', ', ', ', ', '-'], $part);
            }
        }

        return trim(implode('', $parts));
    }

    private function systemPrompt(?User $admin, bool $hasTools): string
    {
        $app = config('app.name', 'MbunieEduHub');
        $name = $admin?->name ?? 'an admin';
        $role = $admin?->isSuperAdmin() ? 'super admin (sees everything)' : 'admin with limited permissions';
        $now = now()->format('l j F Y, H:i').' ('.config('app.timezone').')';
        $extra = $this->settings->extraInstructions();

        $data = $hasTools
            ? "- You can read live data with the tools (orders, payments, users, subscriptions, server errors, support chats, video help calls, products, open problems). Always use a tool for numbers or records instead of guessing. If a tool says the admin lacks permission, say so.\n- Tools are READ ONLY. You cannot change, approve, reject, delete, refund or send anything. Never claim you did. When something must be done, say exactly what to do and give the admin page link from the tool result, and ask the admin to do it there (they confirm the change themselves)."
            : '- You have no live data in this mode. Say so when asked about orders, payments or errors, and suggest switching the model to DeepSeek Chat.';

        return <<<TXT
You are "Mbunie AI", the private assistant of the {$app} admin team (mbuniehub.com, Tanzania: AI tools access, software, Mbunie VPN, live classes, video courses, research library, scholarships). You are talking to {$name}, {$role}. Now: {$now}. Money is in Tanzanian shillings (TZS).

How to answer:
- Reply in the language of the admin's latest message (Swahili or English, or whatever they use).
- Use Markdown: short paragraphs, lists, tables for records, `code` and fenced code blocks with a language tag. Be direct and useful like ChatGPT.
- You can help with anything the team needs: analysis, writing (posts, emails, replies to customers, ads, scripts), planning, code, maths, translations, ideas.
{$data}
- Do not show secrets: passwords, API keys, tokens or full card/phone numbers. Customer data stays inside this chat.

Creating media:
- Posters, banners, flyers, social posts, logos, icons, certificates and illustrations: you design them as SVG. Reply with a short note and ONE complete ```svg code block: root <svg xmlns="http://www.w3.org/2000/svg" width=".." height=".." viewBox="0 0 W H">, everything as attributes (shapes, <defs> gradients, text with font-family="Figtree, Inter, Arial, sans-serif"), no <style> element, no external images, links, fonts or scripts. The page shows it as a picture with PNG and SVG download buttons. Sizes: square post 1080x1080, story 1080x1920, banner 1200x628, web hero 1920x600, A4 poster 1240x1754, YouTube thumbnail 1280x720. Use the brand colours from get_site_info (indigo #4F46E5) unless asked otherwise, strong contrast, big readable text, real product names and prices from the tools when relevant. Keep text inside the canvas (estimate about 0.55 x font-size per character) and wrap long lines with <tspan>.
- Photos or realistic images, voice recordings and videos: you cannot produce those files yet. For audio, write the script; the page has a Listen button that reads any reply aloud. For video, write a script and storyboard (scenes, text on screen, voice over, duration). Say this plainly and offer the SVG or script instead.
- Text meant for the website, posters or customers must not use long dashes (em dash or en dash); use commas, colons or full stops.
{$extra}
TXT;
    }

    private function resolveQuestionId(string $input): ?string
    {
        $normalized = strtolower(trim($input));

        foreach ($this->quickQuestions() as $q) {
            if ($normalized === $q['id'] || $normalized === strtolower($q['label'])) {
                return $q['id'];
            }
        }

        $keywords = [
            'optimization' => ['optimiz', 'database', 'expired', 'expire', 'muda', 'uboreshaji'],
            'orders' => ['order', 'agiz'],
            'payments' => ['payment', 'malipo', 'lipa'],
            'users' => ['user', 'mtumiaji', 'watumiaji', 'wateja', 'customers'],
            'accounts' => ['account', 'akaunti'],
            'subscriptions' => ['subscription', 'usajili'],
            'errors' => ['error', 'hitilafu', 'bug'],
            'contact' => ['contact', 'ujumbe', 'message'],
            'health' => ['health', 'afya', 'ukubwa', 'size'],
        ];

        foreach ($keywords as $id => $terms) {
            foreach ($terms as $term) {
                if (str_contains($normalized, $term)) {
                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0:string,1:?list<string>}
     */
    private function buildAnswer(string $id): array
    {
        return match ($id) {
            'optimization' => $this->formatOptimization(),
            'orders' => $this->formatOrders(),
            'payments' => $this->formatPayments(),
            'users' => $this->formatUsers(),
            'accounts' => $this->formatAccounts(),
            'subscriptions' => $this->formatSubscriptions(),
            'errors' => $this->formatErrors(),
            'contact' => $this->formatContact(),
            'health' => $this->formatHealth(),
            default => [$this->helpText(), null],
        };
    }

    private function unavailable(): string
    {
        return 'That data is not available right now.';
    }

    private function formatOptimization(): array
    {
        $d = $this->tools->call('get_optimization_summary', []);
        if (isset($d['message'])) {
            return ['No optimization scan has been run yet.', ['get_optimization_summary']];
        }

        $lines = [
            "Last scan: {$d['scanned_at']}",
            "Database size: {$d['total_size_mb']} MB ({$d['total_tables']} tables)",
            "Expired: {$d['expired_count']} | Expiring soon: {$d['expiring_soon_count']}",
            "Inactive/archivable: {$d['inactive_count']} | Duplicate: {$d['duplicate_count']}",
            "Missing indexes: {$d['missing_index_count']} | Orphaned rows: {$d['orphaned_count']}",
            "Estimated recovery: {$d['estimated_recovery_mb']} MB",
            "Recommendations pending approval: {$d['pending_recommendations']}",
        ];

        return [implode("\n", $lines), ['get_optimization_summary']];
    }

    private function formatOrders(): array
    {
        $d = $this->tools->call('get_orders_summary', []);
        $revenue = number_format((float) ($d['confirmed_or_paid_revenue'] ?? 0), 2);
        $lines = [
            'Orders by status: '.$this->formatBreakdown($d['by_status'] ?? []),
            "Total revenue (confirmed/paid): TZS {$revenue}",
        ];

        return [implode("\n", $lines), ['get_orders_summary']];
    }

    private function formatPayments(): array
    {
        $d = $this->tools->call('get_payments_summary', []);
        $approved = number_format((float) ($d['approved_amount'] ?? 0), 2);
        $lines = [
            'Payments by status: '.$this->formatBreakdown($d['by_status'] ?? []),
            "Total approved: TZS {$approved}",
        ];

        return [implode("\n", $lines), ['get_payments_summary']];
    }

    private function formatUsers(): array
    {
        $d = $this->tools->call('get_users_summary', []);
        $lines = [
            "Total users: {$d['total']} (active: {$d['active']}, suspended: {$d['suspended']})",
            "New: {$d['new_last_7_days']} (last 7 days), {$d['new_last_30_days']} (last 30 days)",
        ];

        return [implode("\n", $lines), ['get_users_summary']];
    }

    private function formatAccounts(): array
    {
        $d = $this->tools->call('get_accounts_summary', []);

        return ['Accounts by status: '.$this->formatBreakdown($d['by_status'] ?? []), ['get_accounts_summary']];
    }

    private function formatSubscriptions(): array
    {
        $d = $this->tools->call('get_subscriptions_summary', []);

        return ['Subscriptions by status: '.$this->formatBreakdown($d['by_status'] ?? []), ['get_subscriptions_summary']];
    }

    private function formatErrors(): array
    {
        $d = $this->tools->call('get_error_logs_summary', []);
        if (isset($d['message'])) {
            return [$this->unavailable(), ['get_error_logs_summary']];
        }

        $lines = [
            "Unresolved: {$d['unresolved']} | Resolved: {$d['resolved']}",
            'Top exceptions (unresolved): '.$this->formatBreakdown($d['top_unresolved_exceptions'] ?? []),
        ];

        return [implode("\n", $lines), ['get_error_logs_summary']];
    }

    private function formatContact(): array
    {
        $d = $this->tools->call('get_contact_messages_summary', []);
        if (isset($d['message'])) {
            return [$this->unavailable(), ['get_contact_messages_summary']];
        }

        return ["Unread messages: {$d['unread']}", ['get_contact_messages_summary']];
    }

    private function formatHealth(): array
    {
        $d = $this->tools->call('get_database_health', []);

        $status = match ($d['status'] ?? '') {
            'stable' => 'Stable',
            'attention' => 'Needs attention',
            'problem' => 'Problem detected',
            default => (string) ($d['status_label'] ?? ''),
        };

        $lines = [
            "Status: {$status}",
            "Tables: {$d['tables']} | Rows (approx): {$d['rows']} | Size: {$d['size_mb']} MB",
        ];
        foreach ($d['checks'] ?? [] as $check) {
            $lines[] = ($check['ok'] ? '✓' : '✗')." {$check['label']}: {$check['detail']}";
        }

        return [implode("\n", $lines), ['get_database_health']];
    }

    /**
     * @param  array<string,mixed>|Collection<string,mixed>  $breakdown
     */
    private function formatBreakdown(array|Collection $breakdown): string
    {
        $text = collect($breakdown)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');

        return $text !== '' ? $text : 'none';
    }

    private function helpText(): string
    {
        $labels = collect($this->quickQuestions())->pluck('label')->implode(' · ');

        return "I couldn't recognise your question. Pick one of the quick questions below, or type a word like 'orders', 'users' or 'payments': {$labels}";
    }
}
