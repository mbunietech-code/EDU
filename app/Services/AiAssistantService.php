<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Support\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Free, rule-based backend for the admin "AI Assistant" chat — no external
 * API, no cost per use. Answers come from a fixed menu of quick questions
 * (each one maps to a single read-only tool in AiAssistantTools) plus loose
 * keyword matching so typing something close to a quick question still
 * works. Replies come back in the language the admin wrote in (Swahili or
 * English). Nothing here calls out to any third-party service.
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
     * Answer one message: $input is either a quick question id (button
     * click) or free text (typed) — either way we log the exact text the
     * admin "said" as the user message, then resolve and answer it.
     */
    public function answer(AiConversation $conversation, string $input, ?string $questionId = null): AiMessage
    {
        // A typed question sets the language. A button click carries no
        // language of its own, so it follows whatever the admin last typed.
        $lang = ($questionId === null ? Language::detect($input) : null)
            ?? $this->recentLanguage($conversation)
            ?? Language::SWAHILI;

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $input,
        ]);

        if ($conversation->title === null) {
            $conversation->update(['title' => Str::limit($input, 40)]);
        } else {
            $conversation->touch();
        }

        $id = $questionId ?? $this->resolveQuestionId($input);

        [$content, $toolsUsed] = $id !== null
            ? $this->buildAnswer($id, $lang)
            : [$this->helpText($lang), null];

        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $content,
            'tools_used' => $toolsUsed,
        ]);
    }

    /**
     * Language of the most recent typed message in this conversation that
     * clearly was one language (button labels and one-word queries are not).
     */
    private function recentLanguage(AiConversation $conversation): ?string
    {
        foreach ($conversation->messages()->where('role', 'user')->latest('id')->limit(10)->pluck('content') as $text) {
            if ($lang = Language::detect($text)) {
                return $lang;
            }
        }

        return null;
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
    private function buildAnswer(string $id, string $lang): array
    {
        return match ($id) {
            'optimization' => $this->formatOptimization($lang),
            'orders' => $this->formatOrders($lang),
            'payments' => $this->formatPayments($lang),
            'users' => $this->formatUsers($lang),
            'accounts' => $this->formatAccounts($lang),
            'subscriptions' => $this->formatSubscriptions($lang),
            'errors' => $this->formatErrors($lang),
            'contact' => $this->formatContact($lang),
            'health' => $this->formatHealth($lang),
            default => [$this->helpText($lang), null],
        };
    }

    /** Pick the Swahili or English wording. */
    private function t(string $lang, string $sw, string $en): string
    {
        return $lang === Language::ENGLISH ? $en : $sw;
    }

    private function unavailable(string $lang): string
    {
        return $this->t($lang, 'Data hii haipatikani kwa sasa.', 'That data is not available right now.');
    }

    private function formatOptimization(string $lang): array
    {
        $d = $this->tools->call('get_optimization_summary', []);
        if (isset($d['message'])) {
            return [$this->t($lang, 'Bado hakuna scan ya uboreshaji iliyofanywa.', 'No optimization scan has been run yet.'), ['get_optimization_summary']];
        }

        $lines = [
            $this->t($lang, "Scan ya mwisho: {$d['scanned_at']}", "Last scan: {$d['scanned_at']}"),
            $this->t($lang, "Ukubwa wa database: {$d['total_size_mb']} MB (jedwali {$d['total_tables']})", "Database size: {$d['total_size_mb']} MB ({$d['total_tables']} tables)"),
            $this->t($lang, "Zilizoisha: {$d['expired_count']} | Zinazoisha karibuni: {$d['expiring_soon_count']}", "Expired: {$d['expired_count']} | Expiring soon: {$d['expiring_soon_count']}"),
            $this->t($lang, "Zisizotumika/za kuhifadhi: {$d['inactive_count']} | Zinazojirudia: {$d['duplicate_count']}", "Inactive/archivable: {$d['inactive_count']} | Duplicate: {$d['duplicate_count']}"),
            $this->t($lang, "Index zinazokosekana: {$d['missing_index_count']} | Rows zilizovunjika: {$d['orphaned_count']}", "Missing indexes: {$d['missing_index_count']} | Orphaned rows: {$d['orphaned_count']}"),
            $this->t($lang, "Nafasi inayoweza kupatikana: {$d['estimated_recovery_mb']} MB", "Estimated recovery: {$d['estimated_recovery_mb']} MB"),
            $this->t($lang, "Mapendekezo yanayosubiri idhini: {$d['pending_recommendations']}", "Recommendations pending approval: {$d['pending_recommendations']}"),
        ];

        return [implode("\n", $lines), ['get_optimization_summary']];
    }

    private function formatOrders(string $lang): array
    {
        $d = $this->tools->call('get_orders_summary', []);
        $revenue = number_format((float) ($d['confirmed_or_paid_revenue'] ?? 0), 2);
        $lines = [
            $this->t($lang, 'Orders kwa hali: ', 'Orders by status: ').$this->formatBreakdown($d['by_status'] ?? [], $lang),
            $this->t($lang, "Jumla ya mapato (confirmed/paid): TZS {$revenue}", "Total revenue (confirmed/paid): TZS {$revenue}"),
        ];

        return [implode("\n", $lines), ['get_orders_summary']];
    }

    private function formatPayments(string $lang): array
    {
        $d = $this->tools->call('get_payments_summary', []);
        $approved = number_format((float) ($d['approved_amount'] ?? 0), 2);
        $lines = [
            $this->t($lang, 'Payments kwa hali: ', 'Payments by status: ').$this->formatBreakdown($d['by_status'] ?? [], $lang),
            $this->t($lang, "Jumla iliyokubaliwa (approved): TZS {$approved}", "Total approved: TZS {$approved}"),
        ];

        return [implode("\n", $lines), ['get_payments_summary']];
    }

    private function formatUsers(string $lang): array
    {
        $d = $this->tools->call('get_users_summary', []);
        $lines = [
            $this->t($lang, "Jumla ya users: {$d['total']} (active: {$d['active']}, suspended: {$d['suspended']})", "Total users: {$d['total']} (active: {$d['active']}, suspended: {$d['suspended']})"),
            $this->t($lang, "Wapya: {$d['new_last_7_days']} (siku 7 zilizopita), {$d['new_last_30_days']} (siku 30 zilizopita)", "New: {$d['new_last_7_days']} (last 7 days), {$d['new_last_30_days']} (last 30 days)"),
        ];

        return [implode("\n", $lines), ['get_users_summary']];
    }

    private function formatAccounts(string $lang): array
    {
        $d = $this->tools->call('get_accounts_summary', []);

        return [$this->t($lang, 'Accounts kwa hali: ', 'Accounts by status: ').$this->formatBreakdown($d['by_status'] ?? [], $lang), ['get_accounts_summary']];
    }

    private function formatSubscriptions(string $lang): array
    {
        $d = $this->tools->call('get_subscriptions_summary', []);

        return [$this->t($lang, 'Subscriptions kwa hali: ', 'Subscriptions by status: ').$this->formatBreakdown($d['by_status'] ?? [], $lang), ['get_subscriptions_summary']];
    }

    private function formatErrors(string $lang): array
    {
        $d = $this->tools->call('get_error_logs_summary', []);
        if (isset($d['message'])) {
            return [$this->unavailable($lang), ['get_error_logs_summary']];
        }

        $lines = [
            $this->t($lang, "Hazijatatuliwa: {$d['unresolved']} | Zimetatuliwa: {$d['resolved']}", "Unresolved: {$d['unresolved']} | Resolved: {$d['resolved']}"),
            $this->t($lang, 'Exceptions za juu (hazijatatuliwa): ', 'Top exceptions (unresolved): ').$this->formatBreakdown($d['top_unresolved_exceptions'] ?? [], $lang),
        ];

        return [implode("\n", $lines), ['get_error_logs_summary']];
    }

    private function formatContact(string $lang): array
    {
        $d = $this->tools->call('get_contact_messages_summary', []);
        if (isset($d['message'])) {
            return [$this->unavailable($lang), ['get_contact_messages_summary']];
        }

        return [$this->t($lang, "Ujumbe ambao haujasomwa: {$d['unread']}", "Unread messages: {$d['unread']}"), ['get_contact_messages_summary']];
    }

    private function formatHealth(string $lang): array
    {
        $d = $this->tools->call('get_database_health', []);

        $status = match ($d['status'] ?? '') {
            'stable' => $this->t($lang, 'Imara', 'Stable'),
            'attention' => $this->t($lang, 'Inahitaji uangalizi', 'Needs attention'),
            'problem' => $this->t($lang, 'Tatizo limegunduliwa', 'Problem detected'),
            default => (string) ($d['status_label'] ?? ''),
        };

        $lines = [
            $this->t($lang, "Hali: {$status}", "Status: {$status}"),
            $this->t($lang, "Jedwali: {$d['tables']} | Rows (takriban): {$d['rows']} | Ukubwa: {$d['size_mb']} MB", "Tables: {$d['tables']} | Rows (approx): {$d['rows']} | Size: {$d['size_mb']} MB"),
        ];
        foreach ($d['checks'] ?? [] as $check) {
            $lines[] = ($check['ok'] ? '✓' : '✗')." {$check['label']}: {$check['detail']}";
        }

        return [implode("\n", $lines), ['get_database_health']];
    }

    /**
     * @param  array<string,mixed>|Collection<string,mixed>  $breakdown
     */
    private function formatBreakdown(array|Collection $breakdown, string $lang): string
    {
        $text = collect($breakdown)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');

        return $text !== '' ? $text : $this->t($lang, 'hakuna', 'none');
    }

    private function helpText(string $lang): string
    {
        $labels = collect($this->quickQuestions())->pluck('label')->implode(' · ');

        return $this->t(
            $lang,
            "Sikuweza kutambua swali lako. Chagua moja ya maswali yaliyopo chini, au andika neno kama 'orders', 'users', 'malipo': {$labels}",
            "I couldn't recognise your question. Pick one of the quick questions below, or type a word like 'orders', 'users' or 'payments': {$labels}",
        );
    }
}
