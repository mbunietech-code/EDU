<?php

namespace App\Services\Learning;

use App\Models\ActivityLog;
use App\Models\LearningRoom;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guest links: a room's host shares a link; anyone who opens it joins with
 * just a name. Each guest becomes a restricted user row (is_guest) that can
 * reach that one room only (LearningRoom::isVisibleTo, RestrictGuests
 * middleware). With the room's waiting room on, the host lets each guest in.
 *
 * Nothing works unless an admin turned guest links on for the platform.
 */
class GuestAccessService
{
    public const NAME_MAX = 60;

    /** A guest counts as "waiting" while their waiting page keeps checking in. */
    public const WAITING_TIMEOUT_SECONDS = 90;

    public function enabled(): bool
    {
        return LearningRoom::guestLinksEnabled();
    }

    public function setEnabled(bool $enabled, User $actor): void
    {
        Setting::set('learning.guest_links', $enabled ? '1' : '0', 'boolean', 'learning');

        ActivityLog::log($enabled ? 'learning_guest_links_enabled' : 'learning_guest_links_disabled', 'Setting', null, [
            'by' => $actor->id,
        ]);
    }

    /**
     * Host: turn the room's guest link on (a new random link) or off, and set
     * whether guests wait to be let in.
     */
    public function configureRoom(LearningRoom $room, bool $enabled, ?bool $waitingRoom = null): void
    {
        if ($enabled && ! $this->enabled()) {
            throw ValidationException::withMessages(['guest_link' => 'Guest links are turned off for this platform. Ask an administrator.']);
        }

        $changes = [];

        if ($enabled && ! $room->guest_token) {
            $changes['guest_token'] = $this->newCode();
        } elseif (! $enabled && $room->guest_token) {
            $changes['guest_token'] = null; // the old link stops working at once
        }

        if ($waitingRoom !== null) {
            $changes['guest_waiting_room'] = $waitingRoom;
        }

        if ($changes) {
            $room->forceFill($changes)->save();
        }
    }

    /** A short meeting code like "kqz-mwpt-rbh" (26^10 combinations; joining is rate-limited). */
    private function newCode(): string
    {
        do {
            $letters = '';
            for ($i = 0; $i < 10; $i++) {
                $letters .= chr(random_int(97, 122));
            }
            $code = substr($letters, 0, 3).'-'.substr($letters, 3, 4).'-'.substr($letters, 7, 3);
        } while (LearningRoom::withTrashed()->where('guest_token', $code)->exists());

        return $code;
    }

    /** The room behind a guest link, while guest links work. */
    public function roomForToken(string $token): ?LearningRoom
    {
        // Short codes like "abc-defg-hij"; links made before them had 32-character tokens.
        if (! $this->enabled() || ! preg_match('/^([a-z]{3}-[a-z]{4}-[a-z]{3}|[A-Za-z0-9]{32})$/', $token)) {
            return null;
        }

        $room = LearningRoom::query()->where('guest_token', $token)->first();

        return $room && ! $room->isDraft() ? $room : null;
    }

    /** Create the guest for this room (already admitted when the waiting room is off). */
    public function createGuest(LearningRoom $room, string $name): User
    {
        $name = trim(preg_replace('/\s+/u', ' ', strip_tags($name)) ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > self::NAME_MAX) {
            throw ValidationException::withMessages(['name' => 'Enter your name (2 to '.self::NAME_MAX.' characters).']);
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            // Never a real mailbox: the ".invalid" domain cannot receive mail (RFC 2606).
            'email' => 'guest-'.Str::lower((string) Str::uuid()).'@guest.invalid',
            'password' => Str::random(40),
            'status' => 'active',
            'email_verified_at' => now(),
            'is_guest' => true,
            'guest_room_id' => $room->id,
            'guest_admitted_at' => $room->guest_waiting_room ? null : now(),
            'last_seen_at' => now(),
        ])->save();

        return $user;
    }

    public function isAdmitted(User $user): bool
    {
        return $user->isGuest() && $user->guest_admitted_at !== null && $user->guest_denied_at === null;
    }

    /** Guests of this room waiting at the door right now. */
    public function waiting(LearningRoom $room): Collection
    {
        return User::query()
            ->where('is_guest', true)
            ->where('guest_room_id', $room->id)
            ->whereNull('guest_admitted_at')
            ->whereNull('guest_denied_at')
            ->where('last_seen_at', '>=', now()->subSeconds(self::WAITING_TIMEOUT_SECONDS))
            ->orderBy('created_at')
            ->get(['id', 'name', 'created_at']);
    }

    /** Host lets one guest in, or everyone waiting when $guest is null. */
    public function admit(LearningRoom $room, ?User $guest = null): int
    {
        return $this->guestsOf($room, $guest)->whereNull('guest_denied_at')->whereNull('guest_admitted_at')
            ->update(['guest_admitted_at' => now()]);
    }

    public function deny(LearningRoom $room, User $guest): void
    {
        $this->guestsOf($room, $guest)->update(['guest_denied_at' => now(), 'guest_admitted_at' => null]);
    }

    /**
     * What a guest's waiting page shows.
     *
     * @return array{state:string,room_status:string}
     */
    public function status(User $guest, LearningRoom $room): array
    {
        $state = match (true) {
            $guest->guest_denied_at !== null => 'denied',
            $guest->guest_admitted_at !== null => 'admitted',
            default => 'waiting',
        };

        return ['state' => $state, 'room_status' => (string) $room->status];
    }

    private function guestsOf(LearningRoom $room, ?User $guest)
    {
        return User::query()
            ->where('is_guest', true)
            ->where('guest_room_id', $room->id)
            ->when($guest, fn ($q) => $q->whereKey($guest->id));
    }
}
