<?php

namespace App\Services;

use App\Models\OptimizationScan;
use App\Models\User;
use App\Services\Ai\AiWatchdog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * The read-only "tools" the AI Assistant can call (DeepSeek function
 * calling, and the free rule-based fallback). Nothing here changes data:
 * the assistant reads, explains and links to the admin page where a person
 * makes the change. Every tool is tied to a permission from
 * config/permissions.php and is refused for an admin who lacks it
 * (null = super admin only, like the Database page). Results never include
 * passwords, tokens, shared-account credentials or payment proof files.
 */
class AiAssistantTools
{
    /** tool => permission needed (any of the list), null = super admin only */
    public const PERMISSIONS = [
        'get_open_alerts' => [],
        'get_site_info' => [],
        'get_products_catalog' => ['products.view'],
        'get_optimization_summary' => null,
        'get_database_health' => null,
        'get_orders_summary' => ['orders.view'],
        'list_orders' => ['orders.view'],
        'get_order' => ['orders.view'],
        'get_payments_summary' => ['payments.view'],
        'list_payments' => ['payments.view'],
        'list_online_payments' => ['payments.view'],
        'get_users_summary' => ['users.view'],
        'find_users' => ['users.view'],
        'get_user' => ['users.view'],
        'get_accounts_summary' => ['accounts.view'],
        'get_subscriptions_summary' => ['subscriptions.view'],
        'list_subscriptions' => ['subscriptions.view'],
        'get_error_logs_summary' => ['error_logs.view'],
        'list_error_logs' => ['error_logs.view'],
        'get_error_log' => ['error_logs.view'],
        'list_support_chats' => ['chat.view', 'chat.manage'],
        'get_support_chat' => ['chat.view', 'chat.manage'],
        'list_video_help_calls' => ['video_help.answer', 'chat.manage'],
        'get_contact_messages_summary' => ['contact_messages.view'],
    ];

    public function allowed(string $tool, ?User $user): bool
    {
        if (! array_key_exists($tool, self::PERMISSIONS)) {
            return false;
        }
        if ($user === null) {
            return true; // console / internal use
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        $needs = self::PERMISSIONS[$tool];
        if ($needs === null) {
            return false;
        }
        if ($needs === []) {
            return (bool) $user->is_admin;
        }
        foreach ($needs as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * OpenAI / DeepSeek function definitions for the tools this admin may use.
     *
     * @return list<array<string,mixed>>
     */
    public function definitions(?User $user = null): array
    {
        $int = fn (string $d) => ['type' => 'integer', 'description' => $d];
        $str = fn (string $d) => ['type' => 'string', 'description' => $d];
        $none = ['type' => 'object', 'properties' => (object) []];

        $tools = [
            'get_open_alerts' => ['Problems that need a person right now: new server errors, payments waiting for approval, stuck online payments, customers waiting in support chat, missed video help calls, paid orders not yet activated.', $none],
            'get_site_info' => ['Name, web address, contact details and brand colours of the platform. Use before designing posters or banners.', $none],
            'get_products_catalog' => ['Published products (AI tools, software, VPN, courses) with their plans and prices. Use for marketing material and questions about what we sell.', $none],
            'get_optimization_summary' => ['Latest database-optimization scan: expired/inactive/duplicate counts, missing indexes, orphaned rows, pending recommendations.', $none],
            'get_database_health' => ['Database health: table count, approximate rows, size, pending schema changes, failed jobs.', $none],
            'get_orders_summary' => ['Order counts by status and confirmed revenue, optionally for the last N days.', ['type' => 'object', 'properties' => ['days' => $int('Only orders from the last N days. Omit for all time.')]]],
            'list_orders' => ['Recent orders with number, customer, product, plan, amount, status and link. Filter by status (pending, paid, confirmed, active, expired, rejected, cancelled) and/or a search on order number or customer name/email.', ['type' => 'object', 'properties' => ['status' => $str('Order status filter'), 'search' => $str('Order number, customer name or email'), 'days' => $int('Only the last N days'), 'limit' => $int('Max rows, default 15, max 50')]]],
            'get_order' => ['One order in full: customer, product, plan, amount, status, payments, subscription, rejection reason, VPN activation error.', ['type' => 'object', 'properties' => ['id' => $int('Order id'), 'order_number' => $str('Order number')]]],
            'get_payments_summary' => ['Manual payment counts by status and approved total, optionally for the last N days.', ['type' => 'object', 'properties' => ['days' => $int('Only the last N days')]]],
            'list_payments' => ['Recent manual payments (proofs sent by customers) with amount, method, reference, status, minutes waiting and link. Filter by status (pending, approved, rejected).', ['type' => 'object', 'properties' => ['status' => $str('pending, approved or rejected'), 'limit' => $int('Max rows, default 15, max 50')]]],
            'list_online_payments' => ['Recent online (gateway / mobile money) payments with status, network, amount and gateway message. Filter by status (pending, success, failed).', ['type' => 'object', 'properties' => ['status' => $str('pending, success or failed'), 'limit' => $int('Max rows, default 15, max 50')]]],
            'get_users_summary' => ['User totals: active, suspended, new in the last 7 and 30 days.', $none],
            'find_users' => ['Search members by name or email. Returns status, joined date, last seen and link.', ['type' => 'object', 'properties' => ['search' => $str('Name or email (part is fine)'), 'limit' => $int('Max rows, default 10, max 30')], 'required' => ['search']]],
            'get_user' => ['One member: status, joined, last seen, their orders, subscriptions and support chat state.', ['type' => 'object', 'properties' => ['id' => $int('User id'), 'email' => $str('Exact email')]]],
            'get_accounts_summary' => ['Shared account inventory counts by status (no credentials).', $none],
            'get_subscriptions_summary' => ['Subscription counts by status.', $none],
            'list_subscriptions' => ['Subscriptions with customer, product, plan, expiry and status. Use expiring_within_days for ones ending soon.', ['type' => 'object', 'properties' => ['status' => $str('Status filter'), 'expiring_within_days' => $int('Active ones ending within N days'), 'limit' => $int('Max rows, default 15, max 50')]]],
            'get_error_logs_summary' => ['Unresolved vs resolved server errors and the most frequent unresolved exception types.', $none],
            'list_error_logs' => ['Recent server errors: exception, message, file:line, URL, occurrences, last seen, link. Unresolved by default.', ['type' => 'object', 'properties' => ['include_resolved' => ['type' => 'boolean', 'description' => 'Also show resolved ones'], 'limit' => $int('Max rows, default 10, max 30')]]],
            'get_error_log' => ['One server error in detail with the top of its stack trace, to explain the cause and suggest a fix.', ['type' => 'object', 'properties' => ['id' => $int('Error log id')], 'required' => ['id']]],
            'list_support_chats' => ['Customer support chats, newest activity first: customer, last message, who spoke last, minutes the customer has been waiting, unread count, link. waiting_only=true for customers still waiting for a reply.', ['type' => 'object', 'properties' => ['waiting_only' => ['type' => 'boolean', 'description' => 'Only chats where the customer spoke last'], 'limit' => $int('Max rows, default 15, max 40')]]],
            'get_support_chat' => ['The last messages of one customer support chat, to summarise it or draft a reply.', ['type' => 'object', 'properties' => ['id' => $int('Support conversation id'), 'limit' => $int('Messages, default 20, max 60')], 'required' => ['id']]],
            'list_video_help_calls' => ['Recent video help calls (members calling support on video): who, reason, status (ringing, accepted, missed, cancelled), who answered.', ['type' => 'object', 'properties' => ['status' => $str('Status filter'), 'limit' => $int('Max rows, default 15, max 40')]]],
            'get_contact_messages_summary' => ['Unread contact-form messages and the latest few subjects.', $none],
        ];

        $out = [];
        foreach ($tools as $name => [$description, $parameters]) {
            if (! $this->allowed($name, $user)) {
                continue;
            }
            $out[] = ['type' => 'function', 'function' => ['name' => $name, 'description' => $description, 'parameters' => $parameters]];
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public function call(string $name, array $input, ?User $user = null): array
    {
        if (! array_key_exists($name, self::PERMISSIONS)) {
            return ['error' => "Unknown tool: {$name}"];
        }
        if (! $this->allowed($name, $user)) {
            return ['error' => 'This admin does not have permission for that data.'];
        }

        try {
            return match ($name) {
                'get_open_alerts' => $this->getOpenAlerts($user),
                'get_site_info' => $this->getSiteInfo(),
                'get_products_catalog' => $this->getProductsCatalog(),
                'get_optimization_summary' => $this->getOptimizationSummary(),
                'get_database_health' => $this->getDatabaseHealth(),
                'get_orders_summary' => $this->getOrdersSummary($input),
                'list_orders' => $this->listOrders($input),
                'get_order' => $this->getOrder($input),
                'get_payments_summary' => $this->getPaymentsSummary($input),
                'list_payments' => $this->listPayments($input),
                'list_online_payments' => $this->listOnlinePayments($input),
                'get_users_summary' => $this->getUsersSummary(),
                'find_users' => $this->findUsers($input),
                'get_user' => $this->getUser($input),
                'get_accounts_summary' => $this->getAccountsSummary(),
                'get_subscriptions_summary' => $this->getSubscriptionsSummary(),
                'list_subscriptions' => $this->listSubscriptions($input),
                'get_error_logs_summary' => $this->getErrorLogsSummary(),
                'list_error_logs' => $this->listErrorLogs($input),
                'get_error_log' => $this->getErrorLog($input),
                'list_support_chats' => $this->listSupportChats($input),
                'get_support_chat' => $this->getSupportChat($input),
                'list_video_help_calls' => $this->listVideoHelpCalls($input),
                'get_contact_messages_summary' => $this->getContactMessagesSummary(),
            };
        } catch (Throwable $e) {
            return ['error' => 'Tool failed: '.Str::limit($e->getMessage(), 200)];
        }
    }

    // ------------------------------------------------------------------ helpers

    private function limit(array $input, int $default, int $max): int
    {
        $n = (int) ($input['limit'] ?? $default);

        return max(1, min($max, $n > 0 ? $n : $default));
    }

    private function time($value): ?string
    {
        return $value ? Carbon::parse($value)->toDateTimeString() : null;
    }

    private function minutesSince($value): ?int
    {
        return $value ? (int) Carbon::parse($value)->diffInMinutes(now()) : null;
    }

    private function route(string $name, $param): ?string
    {
        try {
            return route($name, $param);
        } catch (Throwable) {
            return null;
        }
    }

    private function maskPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) > 6 ? substr($digits, 0, 4).str_repeat('•', strlen($digits) - 7).substr($digits, -3) : $phone;
    }

    // ------------------------------------------------------------------ tools

    private function getOpenAlerts(?User $user): array
    {
        $problems = app(AiWatchdog::class)->problems();

        if ($user) {
            $problems = array_values(array_filter($problems, fn ($p) => $this->userMaySee($user, $p['permissions'])));
        }

        return [
            'checked_at' => now()->toDateTimeString(),
            'count' => count($problems),
            'problems' => array_map(fn ($p) => collect($p)->only(['kind', 'severity', 'title', 'message', 'url', 'since'])->all(), array_slice($problems, 0, 30)),
        ];
    }

    /** @param list<string> $permissions */
    private function userMaySee(User $user, array $permissions): bool
    {
        if ($user->isSuperAdmin() || $permissions === []) {
            return true;
        }
        foreach ($permissions as $p) {
            if ($user->hasPermission($p)) {
                return true;
            }
        }

        return false;
    }

    private function getSiteInfo(): array
    {
        $setting = fn (string $key) => rescue(fn () => DB::table('settings')->where('key', $key)->value('value'), null, false);

        return [
            'name' => config('app.name', 'MbunieEduHub'),
            'url' => config('app.url'),
            'country' => 'Tanzania',
            'what_we_do' => 'Education hub: managed access to AI tools (sold as products with plans), software, Mbunie VPN, live classes and video courses, a research library and research tools, scholarships.',
            'brand_colours' => ['primary_indigo' => '#4F46E5', 'dark_indigo' => '#3730A3', 'light_indigo' => '#EEF2FF', 'text_dark' => '#111827', 'white' => '#FFFFFF', 'accent_amber' => '#F59E0B'],
            'font' => 'Figtree, Inter, Arial, sans-serif',
            'contact_email' => $setting('mail_from_address'),
        ];
    }

    private function getProductsCatalog(): array
    {
        $products = DB::table('products')->where('status', 'published')->orderBy('name')->limit(60)
            ->get(['id', 'name', 'slug', 'type', 'description', 'price']);
        $plans = DB::table('plans')->whereIn('product_id', $products->pluck('id'))->where('status', 'active')
            ->orderBy('sort_order')->get(['product_id', 'name', 'duration_days', 'price']);

        return [
            'products' => $products->map(fn ($p) => [
                'name' => $p->name,
                'type' => $p->type,
                'page' => url('/ai-tools/'.$p->slug),
                'description' => Str::limit(strip_tags((string) $p->description), 220),
                'plans' => $plans->where('product_id', $p->id)->map(fn ($pl) => [
                    'name' => $pl->name, 'days' => $pl->duration_days, 'price_tzs' => (float) $pl->price,
                ])->values(),
            ])->values(),
        ];
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

    private function orderRows()
    {
        return DB::table('orders')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->leftJoin('products', 'products.id', '=', 'orders.product_id')
            ->leftJoin('plans', 'plans.id', '=', 'orders.plan_id')
            ->select('orders.id', 'orders.order_number', 'orders.status', 'orders.amount', 'orders.created_at', 'orders.confirmed_at',
                'orders.rejection_reason', 'orders.vpn_activation_error', 'orders.user_id',
                'users.name as customer', 'users.email as customer_email', 'products.name as product', 'plans.name as plan');
    }

    private function formatOrder(object $o): array
    {
        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'status' => $o->status,
            'amount_tzs' => (float) $o->amount,
            'customer' => $o->customer,
            'customer_email' => $o->customer_email,
            'product' => $o->product,
            'plan' => $o->plan,
            'created_at' => $this->time($o->created_at),
            'link' => $this->route('admin.orders.show', $o->id),
        ];
    }

    private function listOrders(array $input): array
    {
        $q = $this->orderRows()->orderByDesc('orders.id');
        if (! empty($input['status'])) {
            $q->where('orders.status', (string) $input['status']);
        }
        if (! empty($input['days'])) {
            $q->where('orders.created_at', '>=', now()->subDays((int) $input['days']));
        }
        if (! empty($input['search'])) {
            $s = '%'.trim((string) $input['search']).'%';
            $q->where(fn ($w) => $w->where('orders.order_number', 'like', $s)->orWhere('users.name', 'like', $s)->orWhere('users.email', 'like', $s));
        }

        return ['orders' => $q->limit($this->limit($input, 15, 50))->get()->map(fn ($o) => $this->formatOrder($o))->values()];
    }

    private function getOrder(array $input): array
    {
        $q = $this->orderRows();
        if (! empty($input['id'])) {
            $q->where('orders.id', (int) $input['id']);
        } elseif (! empty($input['order_number'])) {
            $q->where('orders.order_number', trim((string) $input['order_number']));
        } else {
            return ['error' => 'Give an order id or order_number.'];
        }

        $o = $q->first();
        if (! $o) {
            return ['error' => 'Order not found.'];
        }

        return $this->formatOrder($o) + [
            'confirmed_at' => $this->time($o->confirmed_at),
            'rejection_reason' => $o->rejection_reason,
            'vpn_activation_error' => $o->vpn_activation_error,
            'payments' => DB::table('payments')->where('order_id', $o->id)->orderByDesc('id')->limit(10)
                ->get(['id', 'amount', 'payment_method', 'transaction_reference', 'status', 'admin_note', 'created_at'])
                ->map(fn ($p) => (array) $p + ['link' => $this->route('admin.payments.show', $p->id)]),
            'online_payments' => DB::table('gateway_payments')->where('order_id', $o->id)->orderByDesc('id')->limit(10)
                ->get(['id', 'gateway', 'network', 'amount', 'status', 'message', 'created_at']),
            'subscription' => DB::table('subscriptions')->where('order_id', $o->id)->first(['id', 'status', 'start_date', 'expiry_date']),
        ];
    }

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

    private function listPayments(array $input): array
    {
        $q = DB::table('payments')
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('orders', 'orders.id', '=', 'payments.order_id')
            ->select('payments.*', 'users.name as customer', 'orders.order_number')
            ->orderByDesc('payments.id');
        if (! empty($input['status'])) {
            $q->where('payments.status', (string) $input['status']);
        }

        return ['payments' => $q->limit($this->limit($input, 15, 50))->get()->map(fn ($p) => [
            'id' => $p->id,
            'order_number' => $p->order_number,
            'customer' => $p->customer,
            'amount_tzs' => (float) $p->amount,
            'method' => $p->payment_method,
            'reference' => $p->transaction_reference,
            'status' => $p->status,
            'admin_note' => $p->admin_note,
            'submitted_at' => $this->time($p->created_at),
            'minutes_waiting' => $p->status === 'pending' ? $this->minutesSince($p->created_at) : null,
            'link' => $this->route('admin.payments.show', $p->id),
        ])->values()];
    }

    private function listOnlinePayments(array $input): array
    {
        $q = DB::table('gateway_payments')
            ->leftJoin('users', 'users.id', '=', 'gateway_payments.user_id')
            ->leftJoin('orders', 'orders.id', '=', 'gateway_payments.order_id')
            ->select('gateway_payments.*', 'users.name as customer', 'orders.order_number')
            ->orderByDesc('gateway_payments.id');
        if (! empty($input['status'])) {
            $q->where('gateway_payments.status', (string) $input['status']);
        }

        return ['online_payments' => $q->limit($this->limit($input, 15, 50))->get()->map(fn ($g) => [
            'id' => $g->id,
            'order_number' => $g->order_number,
            'order_link' => $g->order_id ? $this->route('admin.orders.show', $g->order_id) : null,
            'customer' => $g->customer,
            'gateway' => $g->gateway,
            'network' => $g->network,
            'phone' => $this->maskPhone($g->phone),
            'amount' => (float) $g->amount,
            'currency' => $g->currency,
            'status' => $g->status,
            'gateway_message' => Str::limit((string) $g->message, 200),
            'started_at' => $this->time($g->created_at),
            'completed_at' => $this->time($g->completed_at),
            'minutes_pending' => $g->status === 'pending' ? $this->minutesSince($g->created_at) : null,
        ])->values()];
    }

    private function getUsersSummary(): array
    {
        $users = $this->realUsersTable();

        return [
            'total' => (clone $users)->count(),
            'active' => (clone $users)->where('status', 'active')->count(),
            'suspended' => (clone $users)->where('status', 'suspended')->count(),
            'new_last_7_days' => (clone $users)->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
            'new_last_30_days' => (clone $users)->where('created_at', '>=', Carbon::now()->subDays(30))->count(),
        ];
    }

    private function realUsersTable()
    {
        $query = DB::table('users');

        if (Schema::hasColumn('users', 'is_guest')) {
            $query->where(fn ($q) => $q->where('is_guest', false)->orWhereNull('is_guest'));
        }

        return $query;
    }

    private function formatUser(object $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'status' => $u->status,
            'is_admin' => (bool) $u->is_admin,
            'joined' => $this->time($u->created_at),
            'last_seen' => $this->time($u->last_seen_at ?? null),
            'link' => $this->route('admin.users.show', $u->id),
        ];
    }

    private function findUsers(array $input): array
    {
        $s = trim((string) ($input['search'] ?? ''));
        if ($s === '') {
            return ['error' => 'Give a name or email to search.'];
        }
        $like = '%'.$s.'%';

        return ['users' => $this->realUsersTable()
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderByDesc('id')
            ->limit($this->limit($input, 10, 30))
            ->get(['id', 'name', 'email', 'status', 'is_admin', 'created_at', 'last_seen_at'])
            ->map(fn ($u) => $this->formatUser($u))->values()];
    }

    private function getUser(array $input): array
    {
        $q = DB::table('users');
        if (! empty($input['id'])) {
            $q->where('id', (int) $input['id']);
        } elseif (! empty($input['email'])) {
            $q->where('email', trim((string) $input['email']));
        } else {
            return ['error' => 'Give a user id or email.'];
        }
        $u = $q->first(['id', 'name', 'email', 'status', 'is_admin', 'created_at', 'last_seen_at']);
        if (! $u) {
            return ['error' => 'User not found.'];
        }

        $conversationId = DB::table('conversations')->where('user_id', $u->id)->value('id');

        return $this->formatUser($u) + [
            'orders' => $this->orderRows()->where('orders.user_id', $u->id)->orderByDesc('orders.id')->limit(10)->get()->map(fn ($o) => $this->formatOrder($o))->values(),
            'subscriptions' => DB::table('subscriptions')
                ->leftJoin('products', 'products.id', '=', 'subscriptions.product_id')
                ->where('subscriptions.user_id', $u->id)->orderByDesc('subscriptions.id')->limit(10)
                ->get(['subscriptions.id', 'products.name as product', 'subscriptions.status', 'subscriptions.start_date', 'subscriptions.expiry_date']),
            'support_chat_link' => $conversationId ? $this->route('admin.chat.show', $conversationId) : null,
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

    private function listSubscriptions(array $input): array
    {
        $q = DB::table('subscriptions')
            ->leftJoin('users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('products', 'products.id', '=', 'subscriptions.product_id')
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select('subscriptions.id', 'subscriptions.status', 'subscriptions.start_date', 'subscriptions.expiry_date',
                'users.name as customer', 'users.email as customer_email', 'products.name as product', 'plans.name as plan');

        if (! empty($input['expiring_within_days'])) {
            $q->whereIn('subscriptions.status', ['active', 'expiring_soon'])
                ->whereBetween('subscriptions.expiry_date', [now(), now()->addDays((int) $input['expiring_within_days'])])
                ->orderBy('subscriptions.expiry_date');
        } else {
            $q->orderByDesc('subscriptions.id');
        }
        if (! empty($input['status'])) {
            $q->where('subscriptions.status', (string) $input['status']);
        }

        return ['subscriptions' => $q->limit($this->limit($input, 15, 50))->get()->map(fn ($s) => (array) $s + [
            'link' => $this->route('admin.subscriptions.show', $s->id),
        ])->values()];
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

    private function listErrorLogs(array $input): array
    {
        $q = DB::table('error_logs')->orderByDesc('last_seen_at')->orderByDesc('id');
        if (empty($input['include_resolved'])) {
            $q->whereNull('resolved_at');
        }

        return ['errors' => $q->limit($this->limit($input, 10, 30))
            ->get(['id', 'level', 'exception', 'message', 'file', 'line', 'url', 'method', 'occurrences', 'first_seen_at', 'last_seen_at', 'resolved_at'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'level' => $e->level,
                'exception' => $e->exception,
                'message' => Str::limit((string) $e->message, 300),
                'where' => $this->shortPath($e->file).':'.$e->line,
                'url' => $e->method.' '.Str::limit((string) $e->url, 120),
                'occurrences' => (int) $e->occurrences,
                'first_seen' => $this->time($e->first_seen_at),
                'last_seen' => $this->time($e->last_seen_at),
                'resolved' => $e->resolved_at !== null,
                'link' => $this->route('admin.error-logs.show', $e->id),
            ])->values()];
    }

    private function shortPath(?string $file): string
    {
        $file = str_replace('\\', '/', (string) $file);
        $base = str_replace('\\', '/', base_path()).'/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : (string) preg_replace('#^.*/public_html/#', '', $file);
    }

    private function getErrorLog(array $input): array
    {
        $e = DB::table('error_logs')->where('id', (int) ($input['id'] ?? 0))->first();
        if (! $e) {
            return ['error' => 'Error log not found.'];
        }

        $trace = collect(preg_split('/\R/', (string) $e->trace))
            ->filter()
            ->map(fn ($l) => Str::limit(str_replace(str_replace('\\', '/', base_path()).'/', '', str_replace('\\', '/', $l)), 220))
            ->take(14)->values();

        return [
            'id' => $e->id,
            'level' => $e->level,
            'exception' => $e->exception,
            'message' => Str::limit((string) $e->message, 800),
            'where' => $this->shortPath($e->file).':'.$e->line,
            'url' => $e->method.' '.Str::limit((string) $e->url, 200),
            'occurrences' => (int) $e->occurrences,
            'first_seen' => $this->time($e->first_seen_at),
            'last_seen' => $this->time($e->last_seen_at),
            'resolved' => $e->resolved_at !== null,
            'trace_top' => $trace,
            'link' => $this->route('admin.error-logs.show', $e->id),
        ];
    }

    private function listSupportChats(array $input): array
    {
        $latest = DB::table('chat_messages')
            ->where(fn ($w) => $w->where('is_deleted', false)->orWhereNull('is_deleted'))
            ->select('conversation_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('conversation_id');

        $rows = DB::table('conversations')
            ->joinSub($latest, 'lm', 'lm.conversation_id', '=', 'conversations.id')
            ->join('chat_messages as m', 'm.id', '=', 'lm.last_id')
            ->leftJoin('users', 'users.id', '=', 'conversations.user_id')
            ->select('conversations.id', 'users.name as customer', 'users.email as customer_email',
                'm.body', 'm.type', 'm.is_from_admin', 'm.is_auto', 'm.created_at as last_at')
            ->orderByDesc('lm.last_id');

        if (! empty($input['waiting_only'])) {
            $rows->where('m.is_from_admin', false);
        }

        $list = $rows->limit($this->limit($input, 15, 40))->get();
        $unread = DB::table('chat_messages')->whereIn('conversation_id', $list->pluck('id'))
            ->where('is_from_admin', false)->where('is_read', false)
            ->select('conversation_id', DB::raw('COUNT(*) as n'))->groupBy('conversation_id')->pluck('n', 'conversation_id');

        return ['chats' => $list->map(fn ($c) => [
            'id' => $c->id,
            'customer' => $c->customer,
            'customer_email' => $c->customer_email,
            'last_message' => $c->type && $c->type !== 'text' ? '['.$c->type.'] '.Str::limit((string) $c->body, 120) : Str::limit((string) $c->body, 200),
            'last_from' => $c->is_from_admin ? ($c->is_auto ? 'auto-reply' : 'admin') : 'customer',
            'last_at' => $this->time($c->last_at),
            'customer_waiting_minutes' => $c->is_from_admin ? null : $this->minutesSince($c->last_at),
            'unread_from_customer' => (int) ($unread[$c->id] ?? 0),
            'link' => $this->route('admin.chat.show', $c->id),
        ])->values()];
    }

    private function getSupportChat(array $input): array
    {
        $c = DB::table('conversations')->leftJoin('users', 'users.id', '=', 'conversations.user_id')
            ->where('conversations.id', (int) ($input['id'] ?? 0))
            ->first(['conversations.id', 'conversations.user_id', 'users.name as customer', 'users.email as customer_email']);
        if (! $c) {
            return ['error' => 'Support chat not found.'];
        }

        $messages = DB::table('chat_messages')->where('conversation_id', $c->id)
            ->where(fn ($w) => $w->where('is_deleted', false)->orWhereNull('is_deleted'))
            ->orderByDesc('id')->limit($this->limit($input, 20, 60))
            ->get(['is_from_admin', 'is_auto', 'type', 'body', 'file_name', 'created_at'])
            ->reverse()->values();

        return [
            'id' => $c->id,
            'customer' => $c->customer,
            'customer_email' => $c->customer_email,
            'customer_link' => $this->route('admin.users.show', $c->user_id),
            'link' => $this->route('admin.chat.show', $c->id),
            'messages' => $messages->map(fn ($m) => [
                'from' => $m->is_from_admin ? ($m->is_auto ? 'auto-reply' : 'admin') : 'customer',
                'text' => $m->type && $m->type !== 'text' ? '['.$m->type.($m->file_name ? ': '.$m->file_name : '').'] '.Str::limit((string) $m->body, 300) : Str::limit((string) $m->body, 600),
                'at' => $this->time($m->created_at),
            ]),
        ];
    }

    private function listVideoHelpCalls(array $input): array
    {
        if (! Schema::hasTable('video_help_requests')) {
            return ['calls' => []];
        }

        $q = DB::table('video_help_requests as v')
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->leftJoin('users as a', 'a.id', '=', 'v.accepted_by')
            ->select('v.id', 'v.status', 'v.reason', 'v.created_at', 'v.accepted_at', 'v.user_id', 'u.name as member', 'u.email as member_email', 'a.name as answered_by')
            ->orderByDesc('v.id');
        if (! empty($input['status'])) {
            $q->where('v.status', (string) $input['status']);
        }

        return [
            'log_page' => $this->route('admin.video-help.index', []),
            'calls' => $q->limit($this->limit($input, 15, 40))->get()->map(fn ($v) => [
                'id' => $v->id,
                'member' => $v->member,
                'member_email' => $v->member_email,
                'member_link' => $this->route('admin.users.show', $v->user_id),
                'reason' => Str::limit((string) $v->reason, 200),
                'status' => $v->status,
                'answered_by' => $v->answered_by,
                'called_at' => $this->time($v->created_at),
            ])->values(),
        ];
    }

    private function getContactMessagesSummary(): array
    {
        if (! Schema::hasTable('contact_messages')) {
            return ['message' => 'contact_messages table not present.'];
        }

        return [
            'unread' => DB::table('contact_messages')->where('is_read', false)->count(),
            'latest_unread' => DB::table('contact_messages')->where('is_read', false)->orderByDesc('id')->limit(5)
                ->get(['name', 'subject', 'created_at']),
        ];
    }

    private function getDatabaseHealth(): array
    {
        return app(DatabaseHealthService::class)->report();
    }
}
