<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Notifications\Admin\AiAlert;
use App\Support\AiAssistantSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * The AI Assistant's watchman. Every minute (ai:watch) it looks for things a
 * person must handle now and "calls" the admins who can handle them: an
 * AiAlert database notification, which rings in the video help popup on
 * every admin page and is pushed to the MHub app (PushDatabaseNotification).
 * Each problem has a fingerprint, so it rings once, not every minute.
 * Rule based on purpose: free, instant and works even when DeepSeek is down.
 * The same list is the get_open_alerts tool of the chat.
 */
class AiWatchdog
{
    /** Older problems are left to the normal admin pages, not rung. */
    private const LOOKBACK_HOURS = 24;

    public function __construct(private AiAssistantSettings $settings)
    {
    }

    /**
     * Current problems, most urgent first.
     *
     * @return list<array{fingerprint:string,kind:string,severity:string,title:string,message:string,url:?string,since:?string,permissions:list<string>,ask:string}>
     */
    public function problems(): array
    {
        $problems = [];

        foreach (['errorProblems', 'paymentProblems', 'onlinePaymentProblems', 'chatProblems', 'videoHelpProblems', 'orderProblems'] as $check) {
            try {
                array_push($problems, ...$this->{$check}());
            } catch (Throwable) {
                // A missing table (alter not applied yet) must not stop the other checks.
            }
        }

        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($problems, fn ($a, $b) => ($rank[$a['severity']] ?? 3) <=> ($rank[$b['severity']] ?? 3));

        return $problems;
    }

    /** Ring the right admins for every problem not rung before. Returns how many problems were new. */
    public function scan(): int
    {
        if (! $this->settings->alertsEnabled()) {
            return 0;
        }

        $admins = User::query()->where('is_admin', true)->get()
            ->filter(fn (User $u) => ! $u->isGuest() && $u->isActive())
            ->values();

        // The first run after switching on only rings what happened in the last hour,
        // not a day of old problems at once.
        $start = $this->settings->watchStartedAt();
        if ($start === null) {
            $this->settings->set('ai_assistant_watch_since', now()->toDateTimeString());
            $start = now();
        }
        $oldest = $start->copy()->subHour();

        $new = 0;
        foreach ($this->problems() as $problem) {
            if ($new >= 10) {
                break; // the rest ring on the next run
            }
            if ($problem['since'] && Carbon::parse($problem['since'])->lt($oldest)) {
                continue;
            }
            if ($problem['kind'] === 'error' && ! $this->ringsFor($problem)) {
                continue; // ErrorOccurred already tells admins; the AI only calls when asked to
            }
            if ($this->alreadyRung($problem['fingerprint'])) {
                continue;
            }

            $to = $this->recipients($admins, $problem['permissions']);
            if ($to->isEmpty()) {
                continue;
            }

            Notification::send($to, new AiAlert($problem));
            $new++;
        }

        return $new;
    }

    /**
     * Server errors already reach admins as a normal notification (ErrorOccurred),
     * so by default the AI does not ring for them. When switched on in settings it
     * rings only for an error that keeps happening (50, 250... times), never for
     * every new one. Customer problems always ring.
     */
    private function ringsFor(array $problem): bool
    {
        return $this->settings->ringForErrors() && str_contains($problem['fingerprint'], ':x');
    }

    /** @param list<string> $permissions */
    private function recipients(Collection $admins, array $permissions): Collection
    {
        return $admins->filter(function (User $u) use ($permissions) {
            if ($u->isSuperAdmin() || $permissions === []) {
                return true;
            }
            foreach ($permissions as $p) {
                if ($u->hasPermission($p)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function alreadyRung(string $fingerprint): bool
    {
        return DB::table('notifications')
            ->where('type', AiAlert::class)
            ->where('data', 'like', '%"fingerprint":"'.$fingerprint.'"%')
            ->exists();
    }

    private function since(): Carbon
    {
        return now()->subHours(self::LOOKBACK_HOURS);
    }

    private function link(string $route, $param = []): ?string
    {
        try {
            return route($route, $param);
        } catch (Throwable) {
            return null;
        }
    }

    private function problem(string $fingerprint, string $kind, string $severity, string $title, string $message, ?string $url, $since, array $permissions, string $ask): array
    {
        return [
            'fingerprint' => $fingerprint,
            'kind' => $kind,
            'severity' => $severity,
            'title' => $title,
            'message' => Str::limit($message, 240),
            'url' => $url,
            'since' => $since ? Carbon::parse($since)->toDateTimeString() : null,
            'permissions' => $permissions,
            'ask' => $ask,
        ];
    }

    // ------------------------------------------------------------------ checks

    /** A new kind of server error (first seen recently), or one that keeps happening. */
    private function errorProblems(): array
    {
        if (! Schema::hasTable('error_logs')) {
            return [];
        }

        $out = [];
        $rows = DB::table('error_logs')
            ->whereNull('resolved_at')
            ->where('last_seen_at', '>=', $this->since())
            ->orderByDesc('last_seen_at')
            ->limit(15)
            ->get(['id', 'level', 'exception', 'message', 'file', 'line', 'occurrences', 'first_seen_at', 'last_seen_at']);

        foreach ($rows as $e) {
            $short = class_basename((string) $e->exception);
            $where = basename(str_replace('\\', '/', (string) $e->file)).':'.$e->line;
            $level = strtolower((string) $e->level);

            if (Carbon::parse($e->first_seen_at)->gte($this->since())) {
                $out[] = $this->problem(
                    "error:{$e->id}", 'error', in_array($level, ['critical', 'alert', 'emergency'], true) ? 'high' : 'medium',
                    'New server error',
                    "{$short} at {$where}: ".Str::limit((string) $e->message, 140),
                    $this->link('admin.error-logs.show', $e->id), $e->first_seen_at, ['error_logs.view'],
                    "Explain server error #{$e->id} and how to fix it.",
                );
            }

            // Counts again each time it crosses 50, 250, 500... occurrences.
            $n = (int) $e->occurrences;
            if ($n >= 50) {
                $step = $n >= 250 ? 250 : 50;
                $bucket = (int) (floor($n / $step) * $step);
                $out[] = $this->problem(
                    "error:{$e->id}:x{$bucket}", 'error', 'high',
                    'Error keeps happening',
                    "{$short} at {$where} has happened {$n} times.",
                    $this->link('admin.error-logs.show', $e->id), $e->last_seen_at, ['error_logs.view'],
                    "Server error #{$e->id} keeps happening. Explain it and how to fix it.",
                );
            }
        }

        return $out;
    }

    /** A payment proof that nobody has approved or rejected yet. */
    private function paymentProblems(): array
    {
        $minutes = $this->settings->paymentWaitMinutes();

        return DB::table('payments')
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->where('payments.status', 'pending')
            ->where('payments.created_at', '<=', now()->subMinutes($minutes))
            ->where('payments.created_at', '>=', $this->since())
            ->orderBy('payments.id')
            ->limit(15)
            ->get(['payments.id', 'payments.amount', 'payments.created_at', 'users.name'])
            ->map(fn ($p) => $this->problem(
                "payment:{$p->id}", 'payment', 'high',
                'Payment waiting for approval',
                ($p->name ?? 'A customer').' sent a payment of TZS '.number_format((float) $p->amount).' '.Carbon::parse($p->created_at)->diffForHumans().' and it is still pending.',
                $this->link('admin.payments.show', $p->id), $p->created_at, ['payments.manage'],
                "Payment #{$p->id} is still pending. Summarise it and tell me what to check before approving.",
            ))->all();
    }

    /** An online (mobile money / card) payment that never finished. */
    private function onlinePaymentProblems(): array
    {
        if (! Schema::hasTable('gateway_payments')) {
            return [];
        }

        return DB::table('gateway_payments')
            ->leftJoin('users', 'users.id', '=', 'gateway_payments.user_id')
            ->where('gateway_payments.status', 'pending')
            ->where('gateway_payments.created_at', '<=', now()->subMinutes(30))
            ->where('gateway_payments.created_at', '>=', $this->since())
            ->orderBy('gateway_payments.id')
            ->limit(10)
            ->get(['gateway_payments.id', 'gateway_payments.order_id', 'gateway_payments.amount', 'gateway_payments.currency', 'gateway_payments.gateway', 'gateway_payments.created_at', 'users.name'])
            ->map(fn ($g) => $this->problem(
                "gateway:{$g->id}", 'payment', 'medium',
                'Online payment stuck',
                ($g->name ?? 'A customer').' started a '.$g->gateway.' payment of '.($g->currency ?: 'TZS').' '.number_format((float) $g->amount).' '.Carbon::parse($g->created_at)->diffForHumans().' and it has not completed.',
                $g->order_id ? $this->link('admin.orders.show', $g->order_id) : $this->link('admin.payments.index'), $g->created_at, ['payments.view'],
                "Online payment #{$g->id} is stuck. What could have happened and what should I do?",
            ))->all();
    }

    /** A customer wrote in support chat and nobody (not even the auto reply) answered yet. */
    private function chatProblems(): array
    {
        $minutes = $this->settings->chatWaitMinutes();

        // The auto "we are running late" reply does not count as an answer.
        $latest = DB::table('chat_messages')
            ->where(fn ($w) => $w->where('is_deleted', false)->orWhereNull('is_deleted'))
            ->where(fn ($w) => $w->where('is_auto', false)->orWhereNull('is_auto'))
            ->select('conversation_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('conversation_id');

        return DB::table('conversations')
            ->joinSub($latest, 'lm', 'lm.conversation_id', '=', 'conversations.id')
            ->join('chat_messages as m', 'm.id', '=', 'lm.last_id')
            ->leftJoin('users', 'users.id', '=', 'conversations.user_id')
            ->where('m.is_from_admin', false)
            ->where('m.created_at', '<=', now()->subMinutes($minutes))
            ->where('m.created_at', '>=', $this->since())
            ->orderBy('m.id')
            ->limit(15)
            ->get(['conversations.id', 'm.id as message_id', 'm.body', 'm.created_at', 'users.name'])
            ->map(fn ($c) => $this->problem(
                "chat:{$c->id}:{$c->message_id}", 'chat', 'high',
                'Customer waiting in chat',
                ($c->name ?? 'A customer').' has been waiting '.Carbon::parse($c->created_at)->diffForHumans(null, true).': "'.Str::limit((string) $c->body, 90).'"',
                $this->link('admin.chat.show', $c->id), $c->created_at, ['chat.manage'],
                "Summarise support chat #{$c->id} and draft a reply for the customer.",
            ))->all();
    }

    /** A member called on video and nobody picked up. */
    private function videoHelpProblems(): array
    {
        if (! Schema::hasTable('video_help_requests')) {
            return [];
        }

        return DB::table('video_help_requests as v')
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->where('v.status', 'missed')
            ->where('v.updated_at', '>=', now()->subHours(6))
            ->orderBy('v.id')
            ->limit(10)
            ->get(['v.id', 'v.user_id', 'v.reason', 'v.created_at', 'u.name'])
            ->map(fn ($v) => $this->problem(
                "videohelp:{$v->id}", 'video_help', 'high',
                'Missed video help call',
                ($v->name ?? 'A member').' called for video help '.Carbon::parse($v->created_at)->diffForHumans().' and nobody answered.'.($v->reason ? ' Reason: '.Str::limit((string) $v->reason, 80) : ''),
                $this->link('admin.users.show', $v->user_id) ?? $this->link('admin.video-help.index'), $v->created_at, ['video_help.answer', 'chat.manage'],
                "A member missed a video help call (#{$v->id}). Who are they and how should we follow up?",
            ))->all();
    }

    /** Paid but still not activated after half an hour. */
    private function orderProblems(): array
    {
        return DB::table('orders')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.status', 'paid')
            ->where('orders.updated_at', '<=', now()->subMinutes(30))
            ->where('orders.updated_at', '>=', $this->since())
            ->orderBy('orders.id')
            ->limit(10)
            ->get(['orders.id', 'orders.order_number', 'orders.updated_at', 'users.name'])
            ->map(fn ($o) => $this->problem(
                "order:{$o->id}:paid", 'order', 'medium',
                'Paid order not activated',
                'Order '.$o->order_number.' from '.($o->name ?? 'a customer').' is paid but not yet active.',
                $this->link('admin.orders.show', $o->id), $o->updated_at, ['orders.manage'],
                "Order {$o->order_number} is paid but not active. What is missing?",
            ))->all();
    }

    /** Ringing alerts for one admin (the popup on every admin page). */
    public function ringingFor(User $admin): Collection
    {
        return $admin->unreadNotifications()
            ->where('type', AiAlert::class)
            ->where('created_at', '>=', now()->subHours(12))
            ->latest()
            ->limit(10)
            ->get();
    }
}
