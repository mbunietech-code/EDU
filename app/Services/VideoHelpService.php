<?php

namespace App\Services;

use App\Models\LearningRoom;
use App\Models\User;
use App\Models\VideoHelpRequest;
use App\Notifications\Admin\VideoHelpCalling;
use App\Notifications\Admin\VideoHelpMissed;
use App\Services\Learning\RoomService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

/**
 * Video help calls: a member asks, every support admin is rung (popup with
 * sound on admin pages, a push to the MHub app, and every registered call
 * device: CallDeviceService) until one accepts. The first
 * accept wins through an atomic "update ... where status = ringing"; that
 * admin opens the support live room and the member is sent into it.
 * Calls nobody answers within ring_seconds become "missed" (checked on
 * every poll and by the scheduler, so it never depends on cron alone).
 */
class VideoHelpService
{
    public function __construct(protected RoomService $rooms)
    {
    }

    public function ringSeconds(): int
    {
        return max(30, (int) config('learning.video_help.ring_seconds', 150));
    }

    public function room(): ?LearningRoom
    {
        $id = (int) config('learning.video_help.room_id', 15);

        return $id > 0 ? LearningRoom::query()->find($id) : null;
    }

    /** Admins who may answer: video_help.answer, or support chat staff (chat.manage). */
    public static function canAnswer(?User $user): bool
    {
        return $user !== null
            && $user->is_admin
            && ! $user->isGuest()
            && $user->isActive()
            && ($user->hasPermission('video_help.answer') || $user->hasPermission('chat.manage'));
    }

    /** @return Collection<int,User> */
    public function answerers(): Collection
    {
        return User::query()
            ->where('is_admin', true)
            ->get()
            ->filter(fn (User $u) => static::canAnswer($u))
            ->values();
    }

    /** The member's call that is still ringing, if any. */
    public function activeFor(User $user): ?VideoHelpRequest
    {
        $this->expireOverdue();

        return VideoHelpRequest::query()
            ->where('user_id', $user->id)
            ->ringing()
            ->latest('id')
            ->first();
    }

    /** Start ringing every support admin (or return the call already ringing). */
    public function request(User $user, ?string $reason): VideoHelpRequest
    {
        if ($existing = $this->activeFor($user)) {
            return $existing;
        }

        $reason = trim((string) $reason);

        $call = VideoHelpRequest::create([
            'user_id' => $user->id,
            'learning_room_id' => $this->room()?->id,
            'status' => VideoHelpRequest::RINGING,
            'reason' => $reason !== '' ? mb_substr($reason, 0, 500) : null,
            'ring_until' => now()->addSeconds($this->ringSeconds()),
        ]);

        $call->setRelation('user', $user);

        // Database notification per admin; PushDatabaseNotification also sends it to their phones (FCM).
        $answerers = $this->answerers();
        $this->notify($answerers, new VideoHelpCalling($call));

        // Computers and phones registered to ring even while signed out; after the response, never blocking the call.
        $ttl = $this->ringSeconds();
        defer(fn () => rescue(fn () => app(CallDeviceService::class)->ring($call, $answerers, $ttl)));

        return $call;
    }

    /** The member hangs up before anyone answered. */
    public function cancel(VideoHelpRequest $call): bool
    {
        return VideoHelpRequest::query()
            ->whereKey($call->id)
            ->ringing()
            ->update(['status' => VideoHelpRequest::CANCELLED, 'ended_at' => now(), 'updated_at' => now()]) === 1;
    }

    /**
     * First admin to accept wins. Returns false when the call was already
     * answered by someone else, cancelled or missed.
     */
    public function accept(VideoHelpRequest $call, User $admin): bool
    {
        if ($call->status === VideoHelpRequest::ACCEPTED && (int) $call->accepted_by === (int) $admin->id) {
            return true; // a double click
        }

        // Refuse before taking the call when the room cannot be opened.
        $room = $this->room();
        if (! $room || $room->isCancelled()) {
            throw ValidationException::withMessages([
                'room' => 'The support live room is not available. Open Studio and publish it again first.',
            ]);
        }

        $now = now();
        $won = VideoHelpRequest::query()
            ->whereKey($call->id)
            ->ringing()
            ->where('ring_until', '>', $now)
            ->update([
                'status' => VideoHelpRequest::ACCEPTED,
                'accepted_by' => $admin->id,
                'accepted_at' => $now,
                'learning_room_id' => $room->id,
                'updated_at' => $now,
            ]) === 1;

        if (! $won) {
            return false;
        }

        // A class whose time is up is ended first, then the room is opened quietly.
        $this->rooms->endIfOverdue($room);
        if (! $room->fresh()?->isLive()) {
            $this->rooms->start($room, $admin, notify: false);
        }

        return true;
    }

    /** This admin stops ringing; when every support admin declined the call is missed. */
    public function decline(VideoHelpRequest $call, User $admin): void
    {
        $everyoneDeclined = DB::transaction(function () use ($call, $admin) {
            $locked = VideoHelpRequest::query()->whereKey($call->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isRinging()) {
                return false;
            }

            $declined = collect($locked->declinedIds())->push((int) $admin->id)->unique()->values()->all();
            $locked->forceFill(['declined_by' => $declined])->save();

            $answerers = $this->answerers()->pluck('id')->map(fn ($id) => (int) $id);

            return $answerers->diff($declined)->isEmpty();
        });

        if ($everyoneDeclined) {
            $this->markMissed($call, notify: false);
        }
    }

    /** Calls still ringing for this admin (not declined by them). */
    public function ringingFor(User $admin): Collection
    {
        $this->expireOverdue();

        return VideoHelpRequest::query()
            ->ringing()
            ->where('ring_until', '>', now())
            ->with('user:id,name,email')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->reject(fn (VideoHelpRequest $c) => in_array((int) $admin->id, $c->declinedIds(), true))
            ->values();
    }

    /** Ringing calls past their time become missed. Returns how many. */
    public function expireOverdue(): int
    {
        $count = 0;

        VideoHelpRequest::query()
            ->ringing()
            ->where('ring_until', '<=', now())
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(function (VideoHelpRequest $call) use (&$count) {
                $count += $this->markMissed($call) ? 1 : 0;
            });

        return $count;
    }

    protected function markMissed(VideoHelpRequest $call, bool $notify = true): bool
    {
        $won = VideoHelpRequest::query()
            ->whereKey($call->id)
            ->ringing()
            ->update(['status' => VideoHelpRequest::MISSED, 'ended_at' => now(), 'updated_at' => now()]) === 1;

        if ($won && $notify) {
            $this->notify($this->answerers(), new VideoHelpMissed($call->fresh(['user']) ?? $call));
        }

        return $won;
    }

    /** What the member's page shows while it polls. */
    public function statusPayload(VideoHelpRequest $call): array
    {
        $call->refresh();

        $payload = [
            'id' => $call->id,
            'status' => $call->status,
            'seconds_left' => $call->isRinging() ? max(0, (int) now()->diffInSeconds($call->ring_until, false)) : 0,
            'join_url' => null,
            'chat_url' => route('user.chat.index'),
        ];

        if ($call->status === VideoHelpRequest::ACCEPTED) {
            $room = $call->room ?? $this->room();
            // "connecting" until the admin's accept has opened the room.
            if ($room && $room->isLive()) {
                $payload['join_url'] = route('learn.rooms.live', ['room' => $room, 'live' => 1]);
            } else {
                $payload['status'] = 'connecting';
            }
            $payload['answered_by'] = $call->acceptedBy?->name;
        }

        return $payload;
    }

    private function notify(Collection $admins, object $notification): void
    {
        if ($admins->isEmpty()) {
            return;
        }

        try {
            Notification::send($admins, $notification);
        } catch (\Throwable $e) {
            report($e); // a failed notification must never stop the call itself
        }
    }
}
