<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Collection;

/**
 * Free, rule-based backend for the admin "AI Assistant" chat — no external
 * API, no cost per use. Answers come from a fixed menu of quick questions
 * (each one maps to a single read-only tool in AiAssistantTools) plus loose
 * keyword matching so typing something close to a quick question still
 * works. Nothing here calls out to any third-party service.
 */
class AiAssistantService
{
    public function __construct(private AiAssistantTools $tools)
    {
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
     * @return \Illuminate\Support\Collection<int,AiConversation>
     */
    public function listFor(int $userId): \Illuminate\Support\Collection
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
     * Answer one message: $input is either a quick question id (button
     * click) or free text (typed) — either way we log the exact text the
     * admin "said" as the user message, then resolve and answer it.
     */
    public function answer(AiConversation $conversation, string $input, ?string $questionId = null): AiMessage
    {
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $input,
        ]);

        if ($conversation->title === null) {
            $conversation->update(['title' => \Illuminate\Support\Str::limit($input, 40)]);
        } else {
            $conversation->touch();
        }

        $id = $questionId ?? $this->resolveQuestionId($input);

        [$content, $toolsUsed] = $id !== null
            ? $this->buildAnswer($id)
            : [$this->helpText(), null];

        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $content,
            'tools_used' => $toolsUsed,
        ]);
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
            'users' => ['user', 'mtumiaji', 'watumiaji', 'wateja'],
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

    private function formatOptimization(): array
    {
        $d = $this->tools->call('get_optimization_summary', []);
        if (isset($d['message'])) {
            return [$d['message'], ['get_optimization_summary']];
        }

        $lines = [
            "Scan ya mwisho: {$d['scanned_at']}",
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
        $lines = [
            'Orders kwa status: '.$this->formatBreakdown($d['by_status'] ?? []),
            'Jumla ya mapato (confirmed/paid): TZS '.number_format((float) ($d['confirmed_or_paid_revenue'] ?? 0), 2),
        ];

        return [implode("\n", $lines), ['get_orders_summary']];
    }

    private function formatPayments(): array
    {
        $d = $this->tools->call('get_payments_summary', []);
        $lines = [
            'Payments kwa status: '.$this->formatBreakdown($d['by_status'] ?? []),
            'Jumla iliyokubaliwa (approved): TZS '.number_format((float) ($d['approved_amount'] ?? 0), 2),
        ];

        return [implode("\n", $lines), ['get_payments_summary']];
    }

    private function formatUsers(): array
    {
        $d = $this->tools->call('get_users_summary', []);
        $lines = [
            "Jumla ya users: {$d['total']} (active: {$d['active']}, suspended: {$d['suspended']})",
            "Wapya: {$d['new_last_7_days']} (siku 7 zilizopita), {$d['new_last_30_days']} (siku 30 zilizopita)",
        ];

        return [implode("\n", $lines), ['get_users_summary']];
    }

    private function formatAccounts(): array
    {
        $d = $this->tools->call('get_accounts_summary', []);

        return ['Accounts kwa status: '.$this->formatBreakdown($d['by_status'] ?? []), ['get_accounts_summary']];
    }

    private function formatSubscriptions(): array
    {
        $d = $this->tools->call('get_subscriptions_summary', []);

        return ['Subscriptions kwa status: '.$this->formatBreakdown($d['by_status'] ?? []), ['get_subscriptions_summary']];
    }

    private function formatErrors(): array
    {
        $d = $this->tools->call('get_error_logs_summary', []);
        if (isset($d['message'])) {
            return [$d['message'], ['get_error_logs_summary']];
        }

        $lines = [
            "Unresolved: {$d['unresolved']} | Resolved: {$d['resolved']}",
            'Exceptions za juu (unresolved): '.($this->formatBreakdown($d['top_unresolved_exceptions'] ?? []) ?: 'hakuna'),
        ];

        return [implode("\n", $lines), ['get_error_logs_summary']];
    }

    private function formatContact(): array
    {
        $d = $this->tools->call('get_contact_messages_summary', []);
        if (isset($d['message'])) {
            return [$d['message'], ['get_contact_messages_summary']];
        }

        return ["Ujumbe ambao haujasomwa: {$d['unread']}", ['get_contact_messages_summary']];
    }

    private function formatHealth(): array
    {
        $d = $this->tools->call('get_database_health', []);
        $lines = [
            "Status: {$d['status_label']}",
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
        return collect($breakdown)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');
    }

    private function helpText(): string
    {
        $labels = collect($this->quickQuestions())->pluck('label')->implode(' · ');

        return "Sikuweza kutambua swali lako. Chagua moja ya maswali yaliyopo chini, au andika neno kama 'orders', 'users', 'payments': {$labels}";
    }
}
