<?php

namespace App\Services;

use App\Models\OptimizationScan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The read-only "tools" the AI Assistant chat can call. Every method here
 * returns small aggregated numbers (counts, sums, groupings) — never raw
 * rows, credentials, payment proofs, or personal data. This is what keeps
 * it safe to send this data to an external LLM: the model only ever sees
 * summaries, the same kind of numbers already shown on admin dashboards.
 */
class AiAssistantTools
{
    /**
     * Anthropic tool-use definitions. Keep descriptions specific — the model
     * decides which tool(s) to call based on these.
     *
     * @return list<array<string,mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'get_optimization_summary',
                'description' => 'Latest database-optimization scan: expired/expiring/inactive/duplicate record counts, missing-index and orphaned-row findings, and how many recommendations are pending approval.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_orders_summary',
                'description' => 'Order counts by status (pending, paid, confirmed, cancelled, rejected) and total revenue from confirmed orders, optionally limited to the last N days.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['days' => ['type' => 'integer', 'description' => 'Limit to orders created in the last N days. Omit for all-time.']],
                ],
            ],
            [
                'name' => 'get_payments_summary',
                'description' => 'Payment counts by status (pending, approved, rejected) and total approved amount, optionally limited to the last N days.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['days' => ['type' => 'integer', 'description' => 'Limit to payments submitted in the last N days. Omit for all-time.']],
                ],
            ],
            [
                'name' => 'get_users_summary',
                'description' => 'Total user count, active/suspended breakdown, and how many new users signed up in the last 7 and 30 days.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_accounts_summary',
                'description' => 'Account inventory counts by status (available, assigned, suspended, expired, maintenance, archived) across all products.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_subscriptions_summary',
                'description' => 'Subscription counts by status (pending, active, expiring_soon, expired, suspended, revoked, cancelled).',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_error_logs_summary',
                'description' => 'How many application errors are unresolved vs resolved, and the most frequent exception types among unresolved errors.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_contact_messages_summary',
                'description' => 'How many contact-form messages are unread.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_database_health',
                'description' => 'Overall database health: table count, approximate row count, size on disk, and any pending schema changes or failed jobs.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public function call(string $name, array $input): array
    {
        try {
            return match ($name) {
                'get_optimization_summary' => $this->getOptimizationSummary(),
                'get_orders_summary' => $this->getOrdersSummary($input),
                'get_payments_summary' => $this->getPaymentsSummary($input),
                'get_users_summary' => $this->getUsersSummary(),
                'get_accounts_summary' => $this->getAccountsSummary(),
                'get_subscriptions_summary' => $this->getSubscriptionsSummary(),
                'get_error_logs_summary' => $this->getErrorLogsSummary(),
                'get_contact_messages_summary' => $this->getContactMessagesSummary(),
                'get_database_health' => $this->getDatabaseHealth(),
                default => ['error' => "Unknown tool: {$name}"],
            };
        } catch (Throwable $e) {
            return ['error' => 'Tool failed: '.$e->getMessage()];
        }
    }

    private function getOptimizationSummary(): array
    {
        $scan = OptimizationScan::latest('id')->first();

        if (! $scan) {
            return ['message' => 'No optimization scan has been run yet.'];
        }

        return [
            'scanned_at' => $scan->created_at->toDateTimeString(),
            'total_tables' => $scan->total_tables,
            'total_size_mb' => (float) $scan->total_size_mb,
            'expired_count' => $scan->expired_count,
            'expiring_soon_count' => $scan->expiring_soon_count,
            'inactive_count' => $scan->inactive_count,
            'duplicate_count' => $scan->duplicate_count,
            'missing_index_count' => $scan->missing_index_count,
            'orphaned_count' => $scan->orphaned_count,
            'estimated_recovery_mb' => (float) $scan->estimated_recovery_mb,
            'pending_recommendations' => $scan->recommendations()->where('status', 'pending')->count(),
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     */
    private function getOrdersSummary(array $input): array
    {
        $query = DB::table('orders');
        if (! empty($input['days'])) {
            $query->where('created_at', '>=', Carbon::now()->subDays((int) $input['days']));
        }

        $byStatus = (clone $query)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $revenue = (clone $query)->whereIn('status', ['confirmed', 'paid'])->sum('amount');

        return [
            'by_status' => $byStatus,
            'confirmed_or_paid_revenue' => (float) $revenue,
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     */
    private function getPaymentsSummary(array $input): array
    {
        $query = DB::table('payments');
        if (! empty($input['days'])) {
            $query->where('created_at', '>=', Carbon::now()->subDays((int) $input['days']));
        }

        $byStatus = (clone $query)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $approvedAmount = (clone $query)->where('status', 'approved')->sum('amount');

        return [
            'by_status' => $byStatus,
            'approved_amount' => (float) $approvedAmount,
        ];
    }

    private function getUsersSummary(): array
    {
        return [
            'total' => DB::table('users')->count(),
            'active' => DB::table('users')->where('status', 'active')->count(),
            'suspended' => DB::table('users')->where('status', 'suspended')->count(),
            'new_last_7_days' => DB::table('users')->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
            'new_last_30_days' => DB::table('users')->where('created_at', '>=', Carbon::now()->subDays(30))->count(),
        ];
    }

    private function getAccountsSummary(): array
    {
        return [
            'by_status' => DB::table('accounts')->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status'),
        ];
    }

    private function getSubscriptionsSummary(): array
    {
        return [
            'by_status' => DB::table('subscriptions')->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status'),
        ];
    }

    private function getErrorLogsSummary(): array
    {
        if (! Schema::hasTable('error_logs')) {
            return ['message' => 'error_logs table not present.'];
        }

        $topUnresolved = DB::table('error_logs')
            ->whereNull('resolved_at')
            ->select('exception', DB::raw('COUNT(*) as total'))
            ->groupBy('exception')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'exception');

        return [
            'unresolved' => DB::table('error_logs')->whereNull('resolved_at')->count(),
            'resolved' => DB::table('error_logs')->whereNotNull('resolved_at')->count(),
            'top_unresolved_exceptions' => $topUnresolved,
        ];
    }

    private function getContactMessagesSummary(): array
    {
        if (! Schema::hasTable('contact_messages')) {
            return ['message' => 'contact_messages table not present.'];
        }

        return [
            'unread' => DB::table('contact_messages')->where('is_read', false)->count(),
        ];
    }

    private function getDatabaseHealth(): array
    {
        return app(DatabaseHealthService::class)->report();
    }
}
