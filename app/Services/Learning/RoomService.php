<?php

namespace App\Services\Learning;

use App\Exceptions\Learning\RoomAccessException;
use App\Models\ActivityLog;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMember;
use App\Models\LearningRoomMessage;
use App\Models\LearningRoomRecording;
use App\Models\LearningRoomSession;
use App\Models\LearningTopic;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lifecycle, attendance and in-room messaging for live rooms. Policies say
 * WHO may act; this service enforces WHEN (the room must be live to join,
 * removed users stay out for the session, cancelled rooms cannot start, …),
 * because Gate::before lets super admins through every policy.
 *
 * Bad input and state violations throw ValidationException (so a form gets
 * its errors back and JSON callers a 422); acting on someone else's message
 * without managing the room throws AuthorizationException (403); join()
 * throws RoomAccessException so the classroom can show the right screen.
 */
class RoomService
{
    /** Longest chat / question / announcement accepted, in characters. */
    public const MAX_MESSAGE_LENGTH = 2000;

    /** A single presence ping never credits more attendance than this. */
    public const MAX_PRESENCE_CREDIT_SECONDS = 60;

    public const MIN_DURATION_MINUTES = 5;

    public const MAX_DURATION_MINUTES = 1440;

    /** Messages sent on the first feed load, and the most any later poll returns. */
    private const FEED_FIRST_PAGE = 100;

    private const FEED_MAX_PAGE = 200;

    /** SMALLINT UNSIGNED ceiling of join_count / peak_participants. */
    private const SMALLINT_MAX = 65535;

    private const TOGGLES = ['chat_enabled', 'questions_enabled', 'allow_participant_media', 'allow_screen_share'];

    public function __construct(
        protected LiveProvider $live,
        protected LearningNotifier $notifier,
        protected LiveServerClient $server,
    ) {
    }

    /**
     * Create a room.
     *
     * $data: title, description, learning_category_id?, learning_course_id?,
     * learning_topic_id?, host_id? (only rooms.manage may name another host;
     * defaults to the actor), scheduled_date + scheduled_time OR scheduled_at,
     * duration_minutes, access, member_ids[] (private rooms), chat_enabled,
     * questions_enabled, allow_participant_media, status ('draft'|'scheduled'),
     * notify (bool).
     *
     * @param  array<string,mixed>  $data
     */
    public function create(array $data, User $actor): LearningRoom
    {
        $attributes = $this->normalise($data, $actor);

        $status = (string) ($data['status'] ?? 'draft');
        if (! in_array($status, ['draft', 'scheduled'], true)) {
            throw ValidationException::withMessages(['status' => 'A new room is saved either as a draft or as scheduled.']);
        }

        if ($status === 'scheduled' && $attributes['scheduled_at'] === null) {
            throw ValidationException::withMessages(['scheduled_date' => 'Pick a date and start time to schedule the room.']);
        }

        $room = DB::transaction(function () use ($attributes, $status, $data, $actor) {
            $room = LearningRoom::create([
                ...$attributes,
                'status' => $status,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if ($room->access === 'private' && array_key_exists('member_ids', $data)) {
                $this->syncMembers($room, $data['member_ids'], $actor);
            }

            return $room;
        });

        ActivityLog::log('learning_room_created', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'status' => $room->status,
            'host_id' => $room->host_id,
        ]);

        if ($status === 'scheduled' && $this->wantsNotify($data)) {
            $this->notifier->notifyRoomScheduled($room, $actor);
        }

        return $room;
    }

    /**
     * Update a room. While live only title, description and the chat /
     * questions / participant-media toggles may change.
     *
     * @param  array<string,mixed>  $data
     */
    public function update(LearningRoom $room, array $data, User $actor): LearningRoom
    {
        $attributes = $this->normalise($data, $actor, $room);

        if ($room->isScheduled() && array_key_exists('scheduled_at', $attributes) && $attributes['scheduled_at'] === null) {
            throw ValidationException::withMessages(['scheduled_date' => 'A scheduled room needs a date and start time.']);
        }

        $changed = DB::transaction(function () use ($room, $attributes, $data, $actor) {
            $room->fill($attributes);

            if ($room->isDirty('scheduled_at')) {
                $room->reminder_sent_at = null; // a new time deserves a new reminder
            }

            $changed = array_keys($room->getDirty());
            $room->updated_by = $actor->id;
            $room->save();

            if (! $room->isLive() && $room->access === 'private' && array_key_exists('member_ids', $data)) {
                $this->syncMembers($room, $data['member_ids'], $actor);
                $changed[] = 'members';
            }

            return $changed;
        });

        ActivityLog::log('learning_room_updated', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'changed' => array_values(array_diff($changed, ['reminder_sent_at'])),
        ]);

        $room->refresh();

        // Media switches changed from the studio form while live: apply them on the SFU now.
        if (array_intersect($changed, ['allow_participant_media', 'allow_screen_share']) !== []) {
            $this->pushPermissionsToPresent($room);
        }

        return $room;
    }

    /** draft | cancelled → scheduled (requires scheduled_at); notifies the audience. */
    public function publish(LearningRoom $room, User $actor, bool $notify = true): void
    {
        DB::transaction(function () use ($room, $actor) {
            $locked = $this->lockRoom($room);

            if (! in_array($locked->status, ['draft', 'cancelled'], true)) {
                throw ValidationException::withMessages(['status' => 'Only draft or cancelled rooms can be published.']);
            }

            if (! $locked->scheduled_at) {
                throw ValidationException::withMessages(['scheduled_date' => 'Set a date and start time before publishing the room.']);
            }

            $locked->forceFill([
                'status' => 'scheduled',
                'cancel_reason' => null,
                'reminder_sent_at' => null,
                'updated_by' => $actor->id,
            ])->save();
        });

        $room->refresh();

        ActivityLog::log('learning_room_published', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'scheduled_at' => $room->scheduled_at?->toIso8601String(),
        ]);

        if ($notify) {
            $this->notifier->notifyRoomScheduled($room, $actor);
        }
    }

    /**
     * Go live from draft | scheduled | completed. Locks the room row; calling
     * it on a room that is already live returns the running session.
     */
    public function start(LearningRoom $room, User $actor): LearningRoomSession
    {
        [$session, $started] = DB::transaction(function () use ($room, $actor) {
            $locked = $this->lockRoom($room);
            $running = $this->openSession($locked);

            if ($locked->isLive() && $running) {
                return [$running, false];
            }

            // A live room without an open session is repaired by opening one.
            if (! $locked->isLive() && ! in_array($locked->status, ['draft', 'scheduled', 'completed'], true)) {
                throw ValidationException::withMessages(['status' => 'A cancelled room cannot be started — publish it again first.']);
            }

            $now = now();

            $locked->forceFill([
                'status' => 'live',
                'started_at' => $now,
                'ended_at' => null,
                'updated_by' => $actor->id,
            ])->save();

            $session = $locked->sessions()->create([
                'started_by' => $actor->id,
                'started_at' => $now,
            ]);

            return [$session, true];
        });

        $room->refresh();
        $this->forgetLiveCounts($room, $actor);

        if ($started) {
            ActivityLog::log('learning_room_started', 'LearningRoom', $room->id, [
                'title' => $room->title,
                'session_id' => $session->id,
            ]);

            $this->notifier->notifyRoomStarted($room, $actor);
        }

        return $session;
    }

    /** End a live room: closes the session and every open attendance. */
    public function end(LearningRoom $room, User $actor): void
    {
        $session = DB::transaction(function () use ($room, $actor) {
            $locked = $this->lockRoom($room);

            if (! $locked->isLive()) {
                throw ValidationException::withMessages(['status' => 'This room is not live.']);
            }

            return $this->closeLiveRoom($locked, $actor);
        });

        $room->refresh();
        $this->forgetLiveCounts($room, $actor);

        ActivityLog::log('learning_room_ended', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'session_id' => $session?->id,
            'duration_seconds' => $session?->durationSeconds(),
        ]);

        // Disconnect everyone (this also stops a running recording).
        $this->server->deleteRoom($this->live->roomName($room));
        Cache::forget(self::browserRecordingKey($room));
    }

    /**
     * The sidebar "Live Rooms" badge is cached per user for 30s; clear it for
     * the actor and host so their own badge flips immediately. Everyone else
     * catches up when their cache entry expires.
     */
    private function forgetLiveCounts(LearningRoom $room, User $actor): void
    {
        foreach (array_unique(array_filter([$actor->id, $room->host_id])) as $userId) {
            Cache::forget('learning.live_count.'.$userId);
        }
    }

    /** draft | scheduled → cancelled; notifies the audience. */
    public function cancel(LearningRoom $room, User $actor, ?string $reason): void
    {
        $reason = trim((string) $reason);
        $reason = $reason === '' ? null : mb_substr($reason, 0, 500);

        $previous = DB::transaction(function () use ($room, $actor, $reason) {
            $locked = $this->lockRoom($room);

            if (! in_array($locked->status, ['draft', 'scheduled'], true)) {
                throw ValidationException::withMessages(['status' => 'Only draft or scheduled rooms can be cancelled.']);
            }

            $previous = $locked->status;

            $locked->forceFill([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'updated_by' => $actor->id,
            ])->save();

            return $previous;
        });

        $room->refresh();

        ActivityLog::log('learning_room_cancelled', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'reason' => $reason,
        ]);

        // A draft was never announced, so there is nobody to tell.
        if ($previous === 'scheduled') {
            $this->notifier->notifyRoomCancelled($room, $actor);
        }
    }

    /**
     * Enter the call: requires a live room, a user not removed from this
     * session and an active account. Upserts the attendance row.
     *
     * @return array{attendance:\App\Models\LearningRoomAttendance,config:array<string,mixed>}
     *
     * Calling it again while in the call (e.g. to reconnect with a fresh
     * token) is safe: the attendance row is reused.
     *
     * @throws \App\Exceptions\Learning\RoomAccessException reason not_live | removed | inactive | locked
     * @throws \RuntimeException when the live server is not configured
     */
    public function join(LearningRoom $room, User $user): array
    {
        if (! $user->isActive()) {
            throw new RoomAccessException('inactive');
        }

        $moderator = $room->isManageableBy($user);

        $attendance = DB::transaction(function () use ($room, $user, $moderator) {
            $session = $this->lockLiveSession($room);

            if (! $session) {
                throw new RoomAccessException('not_live');
            }

            $attendance = LearningRoomAttendance::query()->firstOrNew([
                'learning_room_session_id' => $session->id,
                'user_id' => $user->id,
            ]);

            if ($attendance->removed_at !== null) {
                throw new RoomAccessException('removed');
            }

            // A locked room keeps out newcomers; people already in this session may reconnect.
            if (! $attendance->exists && ! $moderator && $room->fresh()?->is_locked) {
                throw new RoomAccessException('locked');
            }

            $now = now();

            if (! $attendance->exists) {
                $attendance->learning_room_id = $room->id;
                $attendance->first_joined_at = $now;
                $attendance->join_count = 1;
            } else {
                if (! $attendance->isPresent()) {
                    $attendance->join_count = min(self::SMALLINT_MAX, $attendance->join_count + 1);
                }

                $this->credit($attendance, $now);
            }

            $attendance->role = $moderator ? 'host' : 'participant';
            $attendance->last_seen_at = $now;
            $attendance->left_at = null;
            $attendance->save();

            $this->recordPeak($session);

            return $attendance;
        });

        $room->refresh();

        return [
            'attendance' => $attendance,
            'config' => $this->live->clientConfig($user, $room, $this->permissionsFor($room, $user, $attendance), $moderator),
        ];
    }

    /**
     * What this user may publish in the room right now. Room managers may
     * publish everything; everyone else follows the room switches
     * (allow_participant_media for mic + camera, allow_screen_share) unless
     * the host set a personal override on their attendance row.
     *
     * @return array{audio:bool,video:bool,screen:bool}
     */
    public function permissionsFor(LearningRoom $room, User $user, ?LearningRoomAttendance $attendance = null): array
    {
        if ($room->isManageableBy($user)) {
            return ['audio' => true, 'video' => true, 'screen' => true];
        }

        if ($attendance === null && ($session = $this->openSession($room))) {
            $attendance = $this->attendanceFor($session, $user);
        }

        if ($attendance?->removed_at !== null) {
            return ['audio' => false, 'video' => false, 'screen' => false];
        }

        return [
            'audio' => $attendance?->can_publish_audio ?? (bool) $room->allow_participant_media,
            'video' => $attendance?->can_publish_video ?? (bool) $room->allow_participant_media,
            'screen' => $attendance?->can_share_screen ?? (bool) $room->allow_screen_share,
        ];
    }

    /**
     * Presence heartbeat from the classroom page.
     *
     * @return array{status:string,removed:bool}
     */
    public function presence(LearningRoom $room, User $user): array
    {
        return DB::transaction(function () use ($room, $user) {
            $session = $this->lockLiveSession($room);

            if (! $session) {
                return ['status' => $this->currentStatus($room), 'removed' => false];
            }

            $attendance = $this->attendanceFor($session, $user);

            // Pinging without having joined (e.g. the waiting screen) tracks nothing.
            if (! $attendance) {
                return ['status' => 'live', 'removed' => false];
            }

            if ($attendance->removed_at !== null) {
                return ['status' => 'live', 'removed' => true];
            }

            // A ping after leave() (page restored from the back/forward cache)
            // resumes presence without crediting the time away.
            $this->credit($attendance, now());
            $attendance->save();
            $this->recordPeak($session);

            return ['status' => 'live', 'removed' => false];
        });
    }

    public function leave(LearningRoom $room, User $user): void
    {
        DB::transaction(function () use ($room, $user) {
            $session = $this->lockLiveSession($room);
            $attendance = $session ? $this->attendanceFor($session, $user) : null;

            if (! $attendance || ! $this->isOpen($attendance)) {
                return;
            }

            $now = now();
            $this->credit($attendance, $now);
            $attendance->left_at = $now;
            $attendance->save();
        });
    }

    /**
     * Remove a participant from the current session (they cannot rejoin it)
     * and disconnect them from the SFU.
     */
    public function removeParticipant(LearningRoom $room, User $target, User $actor): void
    {
        if ((int) $target->id === (int) $actor->id) {
            throw ValidationException::withMessages(['user' => 'You cannot remove yourself — leave the session instead.']);
        }

        if ($room->isManageableBy($target)) {
            throw ValidationException::withMessages(['user' => 'The host and room managers cannot be removed.']);
        }

        [$attendance, $newlyRemoved] = DB::transaction(function () use ($room, $target, $actor) {
            $session = $this->lockLiveSession($room);

            if (! $session) {
                throw ValidationException::withMessages(['status' => 'Participants can only be removed while the room is live.']);
            }

            $attendance = LearningRoomAttendance::query()->firstOrNew([
                'learning_room_session_id' => $session->id,
                'user_id' => $target->id,
            ]);

            if ($attendance->removed_at !== null) {
                return [$attendance, false];
            }

            $now = now();

            if ($attendance->exists) {
                $this->credit($attendance, $now);
            } else {
                // Not in the call yet — record the block so they cannot come in.
                $attendance->learning_room_id = $room->id;
            }

            $attendance->forceFill([
                'removed_at' => $now,
                'removed_by' => $actor->id,
                'left_at' => $now,
            ])->save();

            return [$attendance, true];
        });

        if ($newlyRemoved) {
            ActivityLog::log('learning_room_participant_removed', 'LearningRoom', $room->id, [
                'title' => $room->title,
                'session_id' => $attendance->learning_room_session_id,
                'user_id' => $target->id,
                'user_name' => $target->name,
            ]);
        }

        // Refused a new token from now on; this closes the connection they already have.
        $this->server->removeParticipant($this->live->roomName($room), $this->live->identityFor($target));
    }

    // --- Moderation (always through Laravel; the SFU only executes) -------
    /** Lock / unlock the room: while locked, only people already in this session (and managers) may join. */
    public function setLocked(LearningRoom $room, bool $locked, User $actor): void
    {
        if ((bool) $room->is_locked === $locked) {
            return;
        }

        $room->forceFill(['is_locked' => $locked, 'updated_by' => $actor->id])->save();

        ActivityLog::log($locked ? 'learning_room_locked' : 'learning_room_unlocked', 'LearningRoom', $room->id, [
            'title' => $room->title,
        ]);
    }

    /**
     * Room-wide publish switches for participants: allow_participant_media
     * (microphone + camera) and allow_screen_share. Personal overrides stay.
     * Connected participants get their new rights at once.
     *
     * @param  array{allow_participant_media?:bool,allow_screen_share?:bool}  $switches
     */
    public function setRoomMedia(LearningRoom $room, array $switches, User $actor): void
    {
        $changes = array_intersect_key($switches, array_flip(['allow_participant_media', 'allow_screen_share']));
        $changes = array_map(fn ($v) => (bool) $v, $changes);

        if ($changes === []) {
            return;
        }

        $room->forceFill($changes + ['updated_by' => $actor->id])->save();

        ActivityLog::log('learning_room_media_changed', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'changes' => $changes,
        ]);

        $this->pushPermissionsToPresent($room);
    }

    /**
     * Personal publish rights for one participant. Each of audio / video /
     * screen is true (allow), false (deny) or null (follow the room switch);
     * keys that are missing stay as they are.
     *
     * @param  array{audio?:?bool,video?:?bool,screen?:?bool}  $rights
     * @return array{audio:bool,video:bool,screen:bool} the effective rights afterwards
     */
    public function setParticipantPermissions(LearningRoom $room, User $target, array $rights, User $actor): array
    {
        if ($room->isManageableBy($target)) {
            throw ValidationException::withMessages(['user' => 'Hosts and room managers always have full media rights.']);
        }

        $columns = ['audio' => 'can_publish_audio', 'video' => 'can_publish_video', 'screen' => 'can_share_screen'];

        $attendance = DB::transaction(function () use ($room, $target, $rights, $columns) {
            $session = $this->lockLiveSession($room);

            if (! $session) {
                throw ValidationException::withMessages(['status' => 'Media rights can only be changed while the room is live.']);
            }

            $attendance = $this->attendanceFor($session, $target);
            if (! $attendance || $attendance->removed_at !== null) {
                throw ValidationException::withMessages(['user' => 'This person is not in the session.']);
            }

            foreach ($columns as $key => $column) {
                if (array_key_exists($key, $rights)) {
                    $attendance->{$column} = $rights[$key] === null ? null : (bool) $rights[$key];
                }
            }

            $attendance->save();

            return $attendance;
        });

        $effective = $this->permissionsFor($room, $target, $attendance);

        ActivityLog::log('learning_room_participant_rights', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'user_id' => $target->id,
            'rights' => $effective,
        ]);

        $this->server->updatePermissions($this->live->roomName($room), $this->live->identityFor($target), $effective);

        return $effective;
    }

    /**
     * Server-side mute of one participant's microphone, camera or screen
     * share. Returns false when the video server could not be reached.
     */
    public function muteParticipant(LearningRoom $room, User $target, string $kind, User $actor): bool
    {
        $this->assertMediaKind($kind);

        if (! $room->isLive()) {
            throw ValidationException::withMessages(['status' => 'The room is not live.']);
        }

        if ((int) $target->id === (int) $actor->id) {
            throw ValidationException::withMessages(['user' => 'Use your own controls to mute yourself.']);
        }

        return $this->server->muteSource($this->live->roomName($room), $this->live->identityFor($target), $kind);
    }

    /**
     * Mute everyone except the room managers.
     *
     * @return int|null participants muted, or null when the video server could not be reached
     */
    public function muteEveryone(LearningRoom $room, string $kind, User $actor): ?int
    {
        $this->assertMediaKind($kind);

        if (! $room->isLive()) {
            throw ValidationException::withMessages(['status' => 'The room is not live.']);
        }

        $hosts = $this->presentParticipants($room)
            ->filter(fn (LearningRoomAttendance $a) => $a->role === 'host')
            ->map(fn (LearningRoomAttendance $a) => $this->live->identityFor((int) $a->user_id))
            ->push($this->live->identityFor($actor))
            ->unique()->values()->all();

        $count = $this->server->muteEveryone($this->live->roomName($room), $kind, $hosts);

        ActivityLog::log('learning_room_mute_all', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'kind' => $kind,
            'count' => $count,
        ]);

        return $this->server->lastError() === null ? $count : null;
    }

    /**
     * Start a server recording (LiveKit Egress) of the running session. A
     * "processing" recording row is created at once and completed by the
     * egress_ended webhook.
     */
    public function startRecording(LearningRoom $room, User $actor): LearningRoomSession
    {
        if (! $this->live->supportsRecording()) {
            throw ValidationException::withMessages(['recording' => 'Server recording is not enabled on this platform.']);
        }

        $session = $room->isLive() ? $this->openSession($room) : null;
        if (! $session) {
            throw ValidationException::withMessages(['recording' => 'Recording can only start while the room is live.']);
        }

        if ($session->isRecording()) {
            return $session;
        }

        $egressId = $this->server->startRecording($this->live->roomName($room));
        if ($egressId === null) {
            throw ValidationException::withMessages(['recording' => $this->server->lastError() ?? 'The recording could not be started.']);
        }

        DB::transaction(function () use ($room, $session, $egressId, $actor) {
            $session->forceFill(['egress_id' => $egressId, 'recording_started_at' => now()])->save();

            LearningRoomRecording::query()->firstOrCreate(['external_id' => $egressId], [
                'learning_room_id' => $room->id,
                'learning_room_session_id' => $session->id,
                'source' => 'livekit',
                'status' => 'processing',
                'uploaded_by' => $actor->id,
            ]);
        });

        ActivityLog::log('learning_room_recording_started', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'session_id' => $session->id,
            'egress_id' => $egressId,
        ]);

        return $session->refresh();
    }

    public function stopRecording(LearningRoom $room, User $actor): void
    {
        $session = $this->openSession($room);

        if (! $session || ! $session->isRecording()) {
            throw ValidationException::withMessages(['recording' => 'No recording is running.']);
        }

        $egressId = (string) $session->egress_id;
        $stopped = $this->server->stopRecording($egressId);

        // The file is finalised by Egress and imported from the egress_ended webhook.
        $session->forceFill(['egress_id' => null])->save();

        ActivityLog::log('learning_room_recording_stopped', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'session_id' => $session->id,
            'egress_id' => $egressId,
            'server_confirmed' => $stopped,
        ]);
    }

    /**
     * The host records the class in their own browser (no Egress on the
     * server). Only a flag is kept, so every participant sees "Recording";
     * the file itself is uploaded as a normal recording when it stops.
     */
    public function setBrowserRecording(LearningRoom $room, bool $recording, User $actor): void
    {
        if ($recording && ! $room->isLive()) {
            throw ValidationException::withMessages(['recording' => 'Recording can only start while the room is live.']);
        }

        $key = self::browserRecordingKey($room);
        $was = Cache::has($key);

        if ($recording) {
            Cache::put($key, $actor->id, now()->addHours(8)); // expires on its own if the tab crashes
        } else {
            Cache::forget($key);
        }

        if ($was !== $recording) {
            ActivityLog::log($recording ? 'learning_room_recording_started' : 'learning_room_recording_stopped', 'LearningRoom', $room->id, [
                'title' => $room->title,
                'mode' => 'browser',
            ]);
        }
    }

    public function isBrowserRecording(LearningRoom $room): bool
    {
        return Cache::has(self::browserRecordingKey($room));
    }

    private static function browserRecordingKey(LearningRoom $room): string
    {
        return 'learning.room.'.$room->id.'.browser_recording';
    }

    /** SFU webhook: the participant left (closed the tab, lost the network, was removed). */
    public function participantDisconnected(LearningRoom $room, User $user): void
    {
        $this->leave($room, $user);
    }

    /** Give every connected non-manager their current rights (after a room-wide switch changed). */
    private function pushPermissionsToPresent(LearningRoom $room): void
    {
        if (! $room->isLive()) {
            return;
        }

        foreach ($this->presentParticipants($room) as $attendance) {
            $user = $attendance->user()->first();

            if (! $user || $room->isManageableBy($user)) {
                continue;
            }

            $this->server->updatePermissions(
                $this->live->roomName($room),
                $this->live->identityFor($user),
                $this->permissionsFor($room, $user, $attendance),
            );
        }
    }

    private function assertMediaKind(string $kind): void
    {
        if (! in_array($kind, LiveProvider::SOURCES, true)) {
            throw ValidationException::withMessages(['kind' => 'Choose audio, video or screen.']);
        }
    }

    /**
     * Attendance rows present right now (with user:id,name).
     *
     * @return Collection<int,\App\Models\LearningRoomAttendance>
     */
    public function presentParticipants(LearningRoom $room): Collection
    {
        $session = $this->openSession($room);

        if (! $session) {
            return collect();
        }

        return LearningRoomAttendance::query()
            ->where('learning_room_session_id', $session->id)
            ->present()
            ->with('user:id,name')
            ->orderBy('first_joined_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Post chat / question / announcement. Only while live; respects the chat
     * and questions toggles; announcements are for room managers only.
     */
    public function postMessage(LearningRoom $room, User $user, string $type, string $body): LearningRoomMessage
    {
        $type = strtolower(trim($type));
        if (! in_array($type, LearningRoomMessage::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Unknown message type.']);
        }

        $body = trim(str_replace("\r\n", "\n", $body));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Write a message first.']);
        }

        if (mb_strlen($body) > self::MAX_MESSAGE_LENGTH) {
            throw ValidationException::withMessages(['body' => 'Messages may not be longer than '.self::MAX_MESSAGE_LENGTH.' characters.']);
        }

        $session = $room->isLive() ? $this->openSession($room) : null;
        if (! $session) {
            throw ValidationException::withMessages(['body' => 'Messages can only be posted while the session is live.']);
        }

        if (! $room->isManageableBy($user)) {
            $rejection = match (true) {
                ! $user->isActive() => 'Your account is not active.',
                $type === 'announcement' => 'Only the host can post announcements.',
                $type === 'chat' && ! $room->chat_enabled => 'The host has turned chat off.',
                $type === 'question' && ! $room->questions_enabled => 'The host has turned questions off.',
                $this->attendanceFor($session, $user)?->removed_at !== null => 'You were removed from this session.',
                default => null,
            };

            if ($rejection !== null) {
                throw ValidationException::withMessages(['body' => $rejection]);
            }
        }

        $message = $room->messages()->create([
            'learning_room_session_id' => $session->id,
            'user_id' => $user->id,
            'type' => $type,
            'body' => $body,
        ]);

        $message->setRelation('room', $room)->setRelation('user', $user);

        if ($type === 'announcement') {
            ActivityLog::log('learning_room_announcement', 'LearningRoom', $room->id, [
                'title' => $room->title,
                'message_id' => $message->id,
            ]);

            $this->notifier->notifyAnnouncement($room, $message, $user);
        }

        return $message;
    }

    public function answerQuestion(LearningRoomMessage $message, User $actor): void
    {
        $room = $message->room;

        if (! $room || ! $room->isManageableBy($actor)) {
            throw new AuthorizationException('Only the host can answer questions.');
        }

        if (! $message->isQuestion() || $message->is_deleted) {
            throw ValidationException::withMessages(['message' => 'Only open questions can be marked as answered.']);
        }

        if ($message->is_answered) {
            return;
        }

        $message->forceFill([
            'is_answered' => true,
            'answered_by' => $actor->id,
            'answered_at' => now(),
        ])->save();
    }

    /** Author or room manager; soft-flags is_deleted. */
    public function deleteMessage(LearningRoomMessage $message, User $actor): void
    {
        $room = $message->room;
        $isAuthor = (int) $message->user_id === (int) $actor->id;

        if (! $isAuthor && ! $room?->isManageableBy($actor)) {
            throw new AuthorizationException('You can only delete your own messages.');
        }

        if ($message->is_deleted) {
            return;
        }

        $message->forceFill(['is_deleted' => true])->save();

        if (! $isAuthor) {
            ActivityLog::log('learning_room_message_deleted', 'LearningRoom', $message->learning_room_id, [
                'message_id' => $message->id,
                'author_id' => $message->user_id,
            ]);
        }
    }

    /**
     * JSON shape of one message for $viewer:
     * ['id','type','body' (empty when deleted),'user'=>['id','name'],'is_host',
     *  'is_answered','is_deleted','created_at' (ISO),'can_delete','can_answer'].
     *
     * @return array<string,mixed>
     */
    public function messagePayload(LearningRoomMessage $m, User $viewer): array
    {
        $room = $m->room;
        $manager = (bool) $room?->isManageableBy($viewer);

        return [
            'id' => $m->id,
            'type' => $m->type,
            'body' => $m->is_deleted ? '' : (string) $m->body,
            'user' => [
                'id' => $m->user_id,
                'name' => $m->user?->name ?? 'Former member',
            ],
            'is_host' => $room !== null && $room->host_id !== null && (int) $room->host_id === (int) $m->user_id,
            'is_answered' => (bool) $m->is_answered,
            'is_deleted' => (bool) $m->is_deleted,
            'created_at' => $m->created_at?->toIso8601String(),
            'can_delete' => ! $m->is_deleted && ($manager || (int) $m->user_id === (int) $viewer->id),
            'can_answer' => $manager && $m->isQuestion() && ! $m->is_deleted && ! $m->is_answered,
        ];
    }

    /**
     * Polling feed for the classroom page:
     * ['cursor' => ISO now,
     *  'room' => ['status','status_label','started_at','chat_enabled','questions_enabled','allow_participant_media',
     *             'allow_screen_share','is_locked','is_recording','title'],
     *  'me' => ['removed','is_host','permissions' => ['audio','video','screen']],
     *  'messages' => [...] (id > $afterId; $afterId = 0 → last 100; max 200; oldest first),
     *  'updates' => [...] (id <= $afterId AND updated_at >= $since),
     *  'participants' => [['user_id','identity','name','role','is_me','permissions'?]] (permissions for managers only),
     *  'counts' => ['participants','questions_open']].
     *
     * @return array<string,mixed>
     */
    public function feed(LearningRoom $room, User $viewer, int $afterId = 0, ?string $since = null): array
    {
        $cursor = now()->toIso8601String();
        $manager = $room->isManageableBy($viewer);
        $afterId = max(0, $afterId);

        $messages = $afterId > 0
            ? $this->roomMessages($room)->where('id', '>', $afterId)->orderBy('id')->limit(self::FEED_MAX_PAGE)->get()
            : $this->roomMessages($room)->orderByDesc('id')->limit(self::FEED_FIRST_PAGE)->get()->reverse()->values();

        $updates = collect();
        $sinceAt = $this->parseCursor($since);
        if ($afterId > 0 && $sinceAt) {
            $updates = $this->roomMessages($room)
                ->where('id', '<=', $afterId)
                ->where('updated_at', '>=', $sinceAt)
                ->orderBy('id')
                ->limit(self::FEED_MAX_PAGE)
                ->get();
        }

        $session = $this->openSession($room);
        $present = $this->presentParticipants($room);
        $myAttendance = $session ? $this->attendanceFor($session, $viewer) : null;

        $payload = fn (LearningRoomMessage $m) => $this->messagePayload($m->setRelation('room', $room), $viewer);

        return [
            'cursor' => $cursor,
            'room' => [
                'status' => $room->status,
                'status_label' => $room->statusLabel(),
                'started_at' => $room->started_at?->toIso8601String(),
                'chat_enabled' => (bool) $room->chat_enabled,
                'questions_enabled' => (bool) $room->questions_enabled,
                'allow_participant_media' => (bool) $room->allow_participant_media,
                'allow_screen_share' => (bool) $room->allow_screen_share,
                'is_locked' => (bool) $room->is_locked,
                'is_recording' => (bool) $session?->isRecording() || ($session !== null && $this->isBrowserRecording($room)),
                'title' => $room->title,
            ],
            'me' => [
                'removed' => $myAttendance?->removed_at !== null,
                'is_host' => $manager,
                'permissions' => $this->permissionsFor($room, $viewer, $myAttendance),
            ],
            'messages' => $messages->map($payload)->values()->all(),
            'updates' => $updates->map($payload)->values()->all(),
            'participants' => $present->map(fn (LearningRoomAttendance $a) => array_filter([
                'user_id' => $a->user_id,
                'identity' => $this->live->identityFor((int) $a->user_id),
                'name' => $a->user?->name ?? 'Member',
                'role' => $a->role,
                'is_me' => (int) $a->user_id === (int) $viewer->id,
                // Only managers see (and change) other people's publish rights.
                'permissions' => $manager && $a->user ? $this->permissionsFor($room, $a->user, $a) : null,
            ], fn ($v) => $v !== null))->values()->all(),
            'counts' => [
                'participants' => $present->count(),
                'questions_open' => LearningRoomMessage::query()
                    ->where('learning_room_id', $room->id)
                    ->where('type', 'question')
                    ->where('is_answered', false)
                    ->where('is_deleted', false)
                    ->count(),
            ],
        ];
    }

    /**
     * Auto-end rooms live longer than duration + stale_room_grace_minutes with nobody present.
     *
     * @return int rooms closed
     */
    public function closeStaleRooms(): int
    {
        $grace = max(0, (int) config('learning.stale_room_grace_minutes', 180));
        $closed = 0;

        LearningRoom::query()
            ->live()
            ->whereNotNull('started_at')
            ->where('started_at', '<=', now()->subMinutes($grace)) // cheap pre-filter; the duration is checked per room
            ->chunkById(100, function ($rooms) use ($grace, &$closed) {
                foreach ($rooms as $room) {
                    if ($room->started_at->copy()->addMinutes((int) $room->duration_minutes + $grace)->isFuture()) {
                        continue;
                    }

                    if ($this->presentParticipants($room)->isNotEmpty()) {
                        continue;
                    }

                    try {
                        // false: someone ended (or restarted) it meanwhile.
                        $session = DB::transaction(function () use ($room) {
                            $locked = $this->lockRoom($room);

                            return $locked->isLive() ? $this->closeLiveRoom($locked, null) : false;
                        });
                    } catch (\Throwable $e) {
                        report($e);

                        continue;
                    }

                    if ($session === false) {
                        continue;
                    }

                    ActivityLog::log('learning_room_ended', 'LearningRoom', $room->id, [
                        'title' => $room->title,
                        'session_id' => $session?->id,
                        'auto' => true,
                    ]);

                    $this->server->deleteRoom($this->live->roomName($room));
                    $closed++;
                }
            });

        return $closed;
    }

    /**
     * Notify the audience of scheduled rooms starting within reminder_minutes
     * (reminder_sent_at still null) and stamp reminder_sent_at.
     *
     * @return int rooms reminded
     */
    public function sendReminders(): int
    {
        $now = now();
        $reminded = 0;

        LearningRoom::query()
            ->where('status', 'scheduled')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $now)
            ->where('scheduled_at', '<=', $now->copy()->addMinutes((int) config('learning.reminder_minutes', 15)))
            ->chunkById(100, function ($rooms) use ($now, &$reminded) {
                foreach ($rooms as $room) {
                    // Stamp first, conditionally, so overlapping runs never remind twice.
                    $claimed = LearningRoom::query()
                        ->whereKey($room->id)
                        ->whereNull('reminder_sent_at')
                        ->update(['reminder_sent_at' => $now]);

                    if ($claimed === 1) {
                        $room->reminder_sent_at = $now;
                        $this->notifier->notifyRoomStartingSoon($room);
                        $reminded++;
                    }
                }
            });

        return $reminded;
    }

    // --- Input -------------------------------------------------------
    /**
     * Validate and normalise create/update input into room attributes.
     * Creating fills every attribute (with defaults); updating only touches
     * the keys present in $data, and a live room only its text and toggles.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function normalise(array $data, User $actor, ?LearningRoom $room = null): array
    {
        $creating = $room === null;
        $has = fn (string ...$keys) => $creating || array_intersect($keys, array_keys($data)) !== [];
        $out = [];

        if ($has('title')) {
            $title = trim((string) ($data['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 255) {
                throw ValidationException::withMessages(['title' => 'Give the room a title of at most 255 characters.']);
            }
            $out['title'] = $title;
        }

        if ($has('description')) {
            $description = trim((string) ($data['description'] ?? ''));
            if (mb_strlen($description) > 20000) {
                throw ValidationException::withMessages(['description' => 'The description is too long.']);
            }
            $out['description'] = $description === '' ? null : $description;
        }

        foreach (self::TOGGLES as $toggle) {
            if (array_key_exists($toggle, $data)) {
                $out[$toggle] = filter_var($data[$toggle], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if ($room?->isLive()) {
            return $out;
        }

        if ($has('learning_category_id', 'learning_course_id', 'learning_topic_id')) {
            $out = [...$out, ...$this->placement($data, $room)];
        }

        if ($has('host_id')) {
            $out['host_id'] = $this->hostId($data, $actor, $room);
        }

        if ($has('scheduled_at', 'scheduled_date', 'scheduled_time')) {
            $out['scheduled_at'] = $this->scheduledAt($data);
        }

        if ($has('duration_minutes')) {
            $raw = $data['duration_minutes'] ?? null;

            if ($raw === null || $raw === '') {
                if ($creating) {
                    $out['duration_minutes'] = 60;
                }
            } else {
                $minutes = filter_var($raw, FILTER_VALIDATE_INT);
                if ($minutes === false || $minutes < self::MIN_DURATION_MINUTES || $minutes > self::MAX_DURATION_MINUTES) {
                    throw ValidationException::withMessages([
                        'duration_minutes' => 'The duration must be between '.self::MIN_DURATION_MINUTES.' and '.self::MAX_DURATION_MINUTES.' minutes.',
                    ]);
                }
                $out['duration_minutes'] = $minutes;
            }
        }

        if ($has('access')) {
            $access = (string) ($data['access'] ?? '');
            $access = $access === '' ? 'public' : $access;
            if (! array_key_exists($access, LearningRoom::ACCESS)) {
                throw ValidationException::withMessages(['access' => 'Choose who may join the room.']);
            }
            $out['access'] = $access;
        }

        // Course / category rooms need something to take their audience from.
        $effective = [...($room?->only(['access', 'learning_course_id', 'learning_category_id']) ?? []), ...$out];

        if (($effective['access'] ?? null) === 'course' && empty($effective['learning_course_id'])) {
            throw ValidationException::withMessages(['learning_course_id' => 'Pick the course whose learners may join.']);
        }

        if (($effective['access'] ?? null) === 'category' && empty($effective['learning_category_id'])) {
            throw ValidationException::withMessages(['learning_category_id' => 'Pick the category whose learners may join.']);
        }

        return $out;
    }

    /**
     * Category / course / topic, kept consistent: a topic implies its course,
     * and a course forces its own category.
     *
     * @param  array<string,mixed>  $data
     * @return array{learning_category_id:?int,learning_course_id:?int,learning_topic_id:?int}
     */
    private function placement(array $data, ?LearningRoom $room): array
    {
        $categoryId = $this->idInput($data, 'learning_category_id', $room?->learning_category_id);
        $courseId = $this->idInput($data, 'learning_course_id', $room?->learning_course_id);
        $topicId = $this->idInput($data, 'learning_topic_id', $room?->learning_topic_id);

        $topic = $topicId ? LearningTopic::query()->find($topicId) : null;
        if ($topicId && ! $topic) {
            throw ValidationException::withMessages(['learning_topic_id' => 'The selected topic does not exist.']);
        }

        $courseId ??= $topic?->learning_course_id;

        $course = $courseId ? LearningCourse::query()->find($courseId) : null;
        if ($courseId && ! $course) {
            throw ValidationException::withMessages(['learning_course_id' => 'The selected course does not exist.']);
        }

        if ($topic && (int) $topic->learning_course_id !== (int) $course->id) {
            throw ValidationException::withMessages(['learning_topic_id' => 'The topic does not belong to the selected course.']);
        }

        if ($course) {
            $categoryId = (int) $course->learning_category_id;
        } elseif ($categoryId && ! LearningCategory::query()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['learning_category_id' => 'The selected category does not exist.']);
        }

        return [
            'learning_category_id' => $categoryId,
            'learning_course_id' => $course?->id,
            'learning_topic_id' => $topic?->id,
        ];
    }

    /** Only rooms.manage may hand a room to someone else, and only to a user who can host. */
    private function hostId(array $data, User $actor, ?LearningRoom $room): int
    {
        $hostId = $this->idInput($data, 'host_id', null) ?? ($room?->host_id ? (int) $room->host_id : (int) $actor->id);

        $unchanged = $hostId === (int) $actor->id || ($room !== null && $hostId === (int) $room->host_id);
        if ($unchanged) {
            return $hostId;
        }

        if (! $actor->hasPermission('rooms.manage')) {
            throw ValidationException::withMessages(['host_id' => 'Only room managers can assign another host.']);
        }

        $host = User::query()->find($hostId);
        if (! $host || ! $host->isActive() || ! $host->canHostRooms()) {
            throw ValidationException::withMessages(['host_id' => 'The selected user cannot host live rooms.']);
        }

        return $hostId;
    }

    /**
     * The start time in the app timezone, from scheduled_date + scheduled_time
     * (form fields) or scheduled_at (string / DateTime); null when blank.
     */
    private function scheduledAt(array $data): ?Carbon
    {
        $timezone = (string) config('app.timezone');
        $date = trim((string) ($data['scheduled_date'] ?? ''));
        $time = trim((string) ($data['scheduled_time'] ?? ''));

        if ($date !== '' || $time !== '') {
            $valid = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $d) === 1
                && preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $t) === 1
                && checkdate((int) $d[2], (int) $d[3], (int) $d[1])
                && (int) $t[1] <= 23 && (int) $t[2] <= 59;

            if (! $valid) {
                throw ValidationException::withMessages(['scheduled_date' => 'Give a valid date (YYYY-MM-DD) and start time (HH:MM).']);
            }

            return Carbon::create((int) $d[1], (int) $d[2], (int) $d[3], (int) $t[1], (int) $t[2], 0, $timezone);
        }

        $value = $data['scheduled_at'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->setTimezone($timezone);
        }

        try {
            return Carbon::parse((string) $value, $timezone)->setTimezone($timezone);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['scheduled_date' => 'The start time is not a valid date.']);
        }
    }

    /** A positive integer id from $data[$key] (blank → null; key absent → $fallback). */
    private function idInput(array $data, string $key, mixed $fallback): ?int
    {
        $value = array_key_exists($key, $data) ? $data[$key] : $fallback;

        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw ValidationException::withMessages([$key => 'The selected value is invalid.']);
        }

        return $id;
    }

    private function wantsNotify(array $data): bool
    {
        return ! array_key_exists('notify', $data) || filter_var($data['notify'], FILTER_VALIDATE_BOOLEAN);
    }

    /** Make the invited-members list exactly $ids (unknown ids and the host are skipped). */
    private function syncMembers(LearningRoom $room, mixed $ids, User $actor): void
    {
        $ids = is_string($ids) ? explode(',', $ids) : (array) $ids;

        $wanted = collect($ids)
            ->flatten()
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $room->host_id !== null && $id === (int) $room->host_id)
            ->unique()
            ->values();

        $valid = $wanted->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $wanted->all())->pluck('id')->map(fn ($id) => (int) $id);

        LearningRoomMember::query()
            ->where('learning_room_id', $room->id)
            ->when($valid->isNotEmpty(), fn ($q) => $q->whereNotIn('user_id', $valid->all()))
            ->delete();

        $existing = LearningRoomMember::query()
            ->where('learning_room_id', $room->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        foreach ($valid->diff($existing) as $userId) {
            $room->members()->create(['user_id' => $userId, 'added_by' => $actor->id]);
        }
    }

    // --- Sessions & attendance ---------------------------------------
    private function lockRoom(LearningRoom $room): LearningRoom
    {
        return LearningRoom::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();
    }

    /** The running session (not locked), or null. */
    private function openSession(LearningRoom $room): ?LearningRoomSession
    {
        return LearningRoomSession::query()
            ->where('learning_room_id', $room->id)
            ->whereNull('ended_at')
            ->latest('id')
            ->first();
    }

    /**
     * The running session, row-locked so concurrent joins / pings of one room
     * serialise (attendance upserts and the peak count stay exact). Null
     * unless the room — re-read, never trusted from the caller — is live.
     */
    private function lockLiveSession(LearningRoom $room): ?LearningRoomSession
    {
        if ($this->currentStatus($room) !== 'live') {
            return null;
        }

        return LearningRoomSession::query()
            ->where('learning_room_id', $room->id)
            ->whereNull('ended_at')
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    /** Status straight from the database; a deleted room reads as completed. */
    private function currentStatus(LearningRoom $room): string
    {
        return (string) (LearningRoom::query()->whereKey($room->getKey())->value('status') ?? 'completed');
    }

    private function attendanceFor(LearningRoomSession $session, User $user): ?LearningRoomAttendance
    {
        return LearningRoomAttendance::query()
            ->where('learning_room_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /** In the call as far as accounting goes: joined, not removed, not left since the last ping. */
    private function isOpen(LearningRoomAttendance $attendance): bool
    {
        return $attendance->removed_at === null
            && $attendance->last_seen_at !== null
            && ($attendance->left_at === null || $attendance->left_at->lt($attendance->last_seen_at));
    }

    /**
     * Credit the time since the last ping (at most MAX_PRESENCE_CREDIT_SECONDS,
     * and only while open) and move last_seen_at to $now. Not saved.
     */
    private function credit(LearningRoomAttendance $attendance, Carbon $now): void
    {
        if ($this->isOpen($attendance)) {
            $gap = max(0, $now->getTimestamp() - $attendance->last_seen_at->getTimestamp());
            $attendance->total_seconds += min($gap, self::MAX_PRESENCE_CREDIT_SECONDS);
        }

        $attendance->last_seen_at = $now;
    }

    private function recordPeak(LearningRoomSession $session): void
    {
        $present = LearningRoomAttendance::query()
            ->where('learning_room_session_id', $session->id)
            ->present()
            ->count();

        if ($present > $session->peak_participants) {
            $session->forceFill(['peak_participants' => min($present, self::SMALLINT_MAX)])->save();
        }
    }

    /**
     * Close the running session(s) of a locked live room: credit and close
     * every open attendance, stamp the session, mark the room completed.
     */
    private function closeLiveRoom(LearningRoom $locked, ?User $actor): ?LearningRoomSession
    {
        $now = now();

        $sessions = LearningRoomSession::query()
            ->where('learning_room_id', $locked->id)
            ->whereNull('ended_at')
            ->orderByDesc('id')
            ->get();

        foreach ($sessions as $session) {
            LearningRoomAttendance::query()
                ->where('learning_room_session_id', $session->id)
                ->whereNull('removed_at')
                ->get()
                ->each(function (LearningRoomAttendance $attendance) use ($now) {
                    if ($this->isOpen($attendance)) {
                        $this->credit($attendance, $now);
                        $attendance->left_at = $now;
                        $attendance->save();
                    }
                });

            $session->forceFill(['ended_at' => $now, 'ended_by' => $actor?->id])->save();
        }

        $locked->forceFill([
            'status' => 'completed',
            'ended_at' => $now,
            'updated_by' => $actor?->id ?? $locked->updated_by,
        ])->save();

        return $sessions->first();
    }

    private function parseCursor(?string $since): ?Carbon
    {
        if ($since === null || trim($since) === '') {
            return null;
        }

        try {
            // Query bindings are formatted without an offset, so compare in the app timezone.
            return Carbon::parse($since)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function roomMessages(LearningRoom $room): Builder
    {
        return LearningRoomMessage::query()
            ->where('learning_room_id', $room->id)
            ->with('user:id,name');
    }
}
