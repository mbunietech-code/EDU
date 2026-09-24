<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Setting;
use App\Support\Language;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends the customer a short automatic message when their chat message has
 * gone unanswered for a while. Free and rule-based — no external AI service.
 *
 * Rules, per conversation:
 *  - the customer's latest (non-deleted) message is older than the configured
 *    number of minutes, but not older than 24h (so switching this on never
 *    blasts old, forgotten chats);
 *  - nobody — human or automatic — has replied since it;
 *  - only ONE automatic reply per waiting stretch: after an auto-reply, the
 *    customer writing again does not trigger another until a person answers.
 *
 * The message goes out in the language the customer wrote in (Swahili or
 * English; Swahili when it can't tell, e.g. a photo or voice note).
 *
 * Runs from the scheduler, and also when the customer's chat is opened or
 * polled, because shared hosting may have no cron. It must never break chat,
 * so every failure is swallowed.
 */
class AutoReplyService
{
    public const DEFAULT_MINUTES = 10;
    public const DEFAULT_MESSAGE = "Habari {name}, asante kwa ujumbe wako. Timu yetu imechelewa kidogo kujibu — tutakujibu mapema iwezekanavyo. Tunaomba uvumilivu wako.";
    public const DEFAULT_MESSAGE_EN = "Hello {name}, thank you for your message. Our team is running a little late in replying — we'll get back to you as soon as possible. Thank you for your patience.";
    private const MAX_AGE_HOURS = 24;

    public function __construct(private NotificationService $notifications)
    {
    }

    public function enabled(): bool
    {
        return (string) Setting::get('autoreply_enabled', '1') === '1';
    }

    public function minutes(): int
    {
        return max(1, (int) Setting::get('autoreply_minutes', self::DEFAULT_MINUTES));
    }

    public function messageTemplate(string $lang = Language::SWAHILI): string
    {
        if ($lang === Language::ENGLISH) {
            $english = trim((string) Setting::get('autoreply_message_en', ''));

            return $english !== '' ? $english : self::DEFAULT_MESSAGE_EN;
        }

        $message = trim((string) Setting::get('autoreply_message', ''));

        return $message !== '' ? $message : self::DEFAULT_MESSAGE;
    }

    /**
     * Check every conversation waiting on a reply; returns how many auto-replies were sent.
     */
    public function processAll(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $sent = 0;

        Conversation::whereHas('messages', fn ($q) => $q
            ->where('is_from_admin', false)
            ->where('is_deleted', false)
            ->whereBetween('created_at', [now()->subHours(self::MAX_AGE_HOURS), now()->subMinutes($this->minutes())])
        )->each(function (Conversation $conversation) use (&$sent) {
            if ($this->processConversation($conversation)) {
                $sent++;
            }
        });

        return $sent;
    }

    public function processConversation(Conversation $conversation): bool
    {
        try {
            if (! $this->enabled()) {
                return false;
            }

            $message = DB::transaction(function () use ($conversation) {
                // Lock the row so the scheduler and a polling customer can't both send.
                $locked = Conversation::whereKey($conversation->id)->lockForUpdate()->first();
                $waitingOn = $locked ? $this->waitingOn($locked) : null;
                if (! $waitingOn) {
                    return null;
                }

                $locked->loadMissing('user');
                $lang = Language::detect((string) $waitingOn->body) ?? Language::SWAHILI;
                $body = str_replace('{name}', $locked->user?->name ?? '', $this->messageTemplate($lang));

                $message = $locked->messages()->create([
                    'is_from_admin' => true,
                    'is_auto' => true,
                    'type' => 'text',
                    'body' => trim($body),
                ]);
                $locked->touch();

                return $message;
            });

            if (! $message) {
                return false;
            }

            $this->notifications->notifyUserNewChatMessage($conversation);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * The customer message still waiting for an answer, or null when no
     * auto-reply is due.
     */
    private function waitingOn(Conversation $conversation): ?\App\Models\ChatMessage
    {
        $lastCustomer = $conversation->messages()
            ->where('is_from_admin', false)
            ->where('is_deleted', false)
            ->latest('id')
            ->first();

        if (! $lastCustomer
            || $lastCustomer->created_at->gt(now()->subMinutes($this->minutes()))
            || $lastCustomer->created_at->lt(now()->subHours(self::MAX_AGE_HOURS))) {
            return null;
        }

        // Someone (a person or an earlier auto-reply) already answered it.
        if ($conversation->messages()->where('is_from_admin', true)->where('id', '>', $lastCustomer->id)->exists()) {
            return null;
        }

        // Only one auto-reply until a person actually responds.
        $lastHuman = (int) $conversation->messages()->where('is_from_admin', true)->where('is_auto', false)->max('id');

        return $conversation->messages()->where('is_auto', true)->where('id', '>', $lastHuman)->exists()
            ? null
            : $lastCustomer;
    }
}
