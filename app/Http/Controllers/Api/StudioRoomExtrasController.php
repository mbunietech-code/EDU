<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMaterial;
use App\Models\LearningRoomMember;
use App\Models\LearningRoomRecording;
use App\Models\LearningVideo;
use App\Services\Learning\LearningDeletionService;
use App\Services\Learning\LearningStorage;
use App\Services\Learning\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Teaching Studio in the app, outside the live call: recordings, teaching
 * materials, invited members and the attendance report of a class.
 * Same services and rules as the web Studio controllers.
 */
class StudioRoomExtrasController extends Controller
{
    /** Everything the class page in the app shows besides the details. */
    public function show(Request $request, LearningRoom $room): JsonResponse
    {
        $this->can($request, 'viewAttendance', $room);
        $user = $request->user();

        return response()->json(['data' => [
            'can_manage' => Gate::forUser($user)->allows('manage', $room),
            'is_private' => $room->access === 'private',
            'recordings' => $room->recordings()->with('session:id,started_at')->latest('id')->limit(50)->get()
                ->map(fn (LearningRoomRecording $r) => $this->recordingRow($room, $r, $user->id))->values(),
            'materials' => $room->materials()->latest('id')->limit(50)->get()
                ->map(fn (LearningRoomMaterial $m) => $this->materialRow($m->setRelation('room', $room)))->values(),
            'members' => $room->members()->with('user:id,name,email')->latest('id')->limit(200)->get()
                ->map(fn (LearningRoomMember $m) => [
                    'id' => $m->id,
                    'user_id' => $m->user_id,
                    'name' => $m->user?->name ?? 'Former member',
                    'email' => $user->is_admin ? $m->user?->email : null,
                ])->values(),
            'sessions' => $room->sessions()->limit(30)->get(['id', 'learning_room_id', 'started_at', 'ended_at', 'peak_participants'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'started_at' => $s->started_at?->toIso8601String(),
                    'ended_at' => $s->ended_at?->toIso8601String(),
                ])->values(),
        ]]);
    }

    /** Attendance of one session (?session=, default the latest). */
    public function attendance(Request $request, LearningRoom $room): JsonResponse
    {
        $this->can($request, 'viewAttendance', $room);
        $sessionId = (int) $request->query('session', 0);
        $session = $sessionId > 0
            ? $room->sessions()->whereKey($sessionId)->firstOrFail()
            : $room->sessions()->first();

        if (! $session) {
            return response()->json(['data' => null]);
        }

        $base = LearningRoomAttendance::query()->where('learning_room_session_id', $session->id);
        $count = (clone $base)->count();
        $sum = (int) (clone $base)->sum('total_seconds');
        $showEmail = (bool) $request->user()->is_admin;

        return response()->json(['data' => [
            'session' => [
                'id' => $session->id,
                'started_at' => $session->started_at?->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
                'duration_seconds' => $session->durationSeconds(),
            ],
            'totals' => [
                'attendees' => $count,
                'removed' => (clone $base)->whereNotNull('removed_at')->count(),
                'total_seconds' => $sum,
                'average_seconds' => $count > 0 ? intdiv($sum, $count) : 0,
                'peak' => (int) $session->peak_participants,
            ],
            'rows' => (clone $base)->with('user:id,name,email')
                ->orderByRaw("CASE WHEN role = 'host' THEN 0 ELSE 1 END")
                ->orderBy('first_joined_at')
                ->limit(500)
                ->get()
                ->map(fn (LearningRoomAttendance $a) => [
                    'name' => $a->user?->name ?? 'Former member',
                    'email' => $showEmail ? $a->user?->email : null,
                    'role' => $a->role,
                    'first_joined_at' => $a->first_joined_at?->toIso8601String(),
                    'total_seconds' => (int) $a->total_seconds,
                    'join_count' => (int) $a->join_count,
                    'removed' => $a->removed_at !== null,
                ])->values(),
        ]]);
    }

    // --- Recordings -------------------------------------------------------

    /** Attach a finished chunked upload (purpose "recording"). */
    public function storeRecording(Request $request, LearningRoom $room, LearningStorage $storage): JsonResponse
    {
        $this->can($request, 'manage', $room);
        $user = $request->user();

        $data = $request->validate([
            'upload_token' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{40}\z/'],
            'learning_room_session_id' => ['nullable', 'integer',
                Rule::exists('learning_room_sessions', 'id')->where('learning_room_id', $room->id)],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        $file = $storage->adoptUpload($user, $data['upload_token'], 'recording', 'learning/recordings');

        $recording = $room->recordings()->create([
            'learning_room_session_id' => $data['learning_room_session_id'] ?? null,
            'source' => 'upload',
            'status' => 'ready',
            'disk' => $file['disk'],
            'path' => $file['path'],
            'original_name' => $file['original_name'],
            'mime' => $file['mime'],
            'size_bytes' => $file['size_bytes'],
            'is_shared' => $request->boolean('is_shared'),
            'uploaded_by' => $user->id,
        ]);

        ActivityLog::log('learning_room_recording_uploaded', 'LearningRoomRecording', $recording->id, [
            'room_id' => $room->id,
            'size_bytes' => $recording->size_bytes,
        ]);

        return response()->json(['data' => $this->recordingRow($room, $recording, $user->id), 'message' => 'Recording uploaded.'], 201);
    }

    public function shareRecording(Request $request, LearningRoom $room, LearningRoomRecording $recording): JsonResponse
    {
        $this->can($request, 'manage', $room);
        $this->owns($room, $recording);

        $data = $request->validate(['is_shared' => ['required', 'boolean']]);
        $recording->forceFill(['is_shared' => (bool) $data['is_shared']])->save();

        return response()->json([
            'data' => $this->recordingRow($room, $recording, $request->user()->id),
            'message' => $recording->is_shared ? 'Learners who can see the class can watch it.' : 'Only room staff can watch it now.',
        ]);
    }

    /** Turn a recording into a draft lesson (Studio → Lessons). */
    public function publishRecording(Request $request, LearningRoom $room, LearningRoomRecording $recording, VideoService $videos): JsonResponse
    {
        $this->can($request, 'manage', $room);
        abort_unless(Gate::forUser($request->user())->allows('create', LearningVideo::class), 403);
        $this->owns($room, $recording);

        $video = $videos->createFromRecording($recording, $request->user());

        return response()->json([
            'message' => 'Draft lesson “'.$video->title.'” created. Review it under Lessons, then publish it.',
            'video_id' => $video->id,
        ], 201);
    }

    public function destroyRecording(Request $request, LearningRoom $room, LearningRoomRecording $recording, LearningDeletionService $deletions): JsonResponse
    {
        $this->can($request, 'manage', $room);
        $this->owns($room, $recording);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        try {
            $deletions->deleteRecording($recording, trim($data['reason']));
        } catch (LearningDeletionBlocked $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Recording deleted.']);
    }

    // --- Materials --------------------------------------------------------

    public function storeMaterial(Request $request, LearningRoom $room, LearningStorage $storage): JsonResponse
    {
        $this->can($request, 'moderate', $room);
        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:'.(max(1, (int) config('learning.max_resource_mb', 50)) * 1024)],
        ]);

        $stored = $storage->storeResourceFile($request->file('file'));
        $title = trim((string) $request->input('title'));
        $material = $room->materials()->create($stored + [
            'title' => Str::limit($title !== '' ? $title : pathinfo($stored['original_name'], PATHINFO_FILENAME), 255, ''),
            'uploaded_by' => $request->user()->id,
        ]);

        ActivityLog::log('learning_room_material_added', 'LearningRoom', $room->id, ['title' => $room->title, 'material_id' => $material->id]);

        return response()->json(['data' => $this->materialRow($material->setRelation('room', $room)), 'message' => 'Material added to the class.'], 201);
    }

    public function destroyMaterial(Request $request, LearningRoom $room, LearningRoomMaterial $material, LearningStorage $storage): JsonResponse
    {
        $this->can($request, 'moderate', $room);
        abort_unless((int) $material->learning_room_id === (int) $room->id, 404);

        $storage->deleteFile($material->disk, $material->path);
        $material->delete();

        ActivityLog::log('learning_room_material_deleted', 'LearningRoom', $room->id, ['title' => $room->title, 'material_id' => $material->id]);

        return response()->json(['message' => 'Material removed.']);
    }

    // --- Invited members (private classes) --------------------------------

    public function storeMembers(Request $request, LearningRoom $room): JsonResponse
    {
        $this->can($request, 'manage', $room);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:200'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('status', 'active')],
        ], ['user_ids.*.exists' => 'Only active members can be invited.']);

        if ($room->access !== 'private') {
            throw ValidationException::withMessages(['user_ids' => 'Invitations only apply to private classes. Change the class to "Invited members only" first.']);
        }

        $added = 0;
        foreach (array_unique(array_map('intval', $data['user_ids'])) as $userId) {
            if ($room->host_id !== null && $userId === (int) $room->host_id) {
                continue;
            }
            $member = LearningRoomMember::query()->firstOrCreate(
                ['learning_room_id' => $room->id, 'user_id' => $userId],
                ['added_by' => $request->user()->id],
            );
            $added += $member->wasRecentlyCreated ? 1 : 0;
        }

        if ($added > 0) {
            ActivityLog::log('learning_room_members_added', 'LearningRoom', $room->id, ['title' => $room->title, 'added' => $added]);
        }

        return response()->json(['message' => $added > 0 ? $added.' invited.' : 'Those members were already invited.', 'added' => $added]);
    }

    public function destroyMember(Request $request, LearningRoom $room, LearningRoomMember $member): JsonResponse
    {
        $this->can($request, 'manage', $room);
        abort_unless((int) $member->learning_room_id === (int) $room->id, 404);

        $member->delete();
        ActivityLog::log('learning_room_member_removed', 'LearningRoom', $room->id, ['title' => $room->title, 'user_id' => $member->user_id]);

        return response()->json(['message' => 'Invitation removed.']);
    }

    // --- Helpers ----------------------------------------------------------

    private function recordingRow(LearningRoom $room, LearningRoomRecording $r, int $viewerId): array
    {
        $playable = $r->isReady() && $r->disk && $r->path;

        return [
            'id' => $r->id,
            'status' => $r->status,
            'source' => $r->source,
            'name' => $r->original_name,
            'size' => $r->sizeLabel(),
            'is_shared' => (bool) $r->is_shared,
            'is_published' => $r->learning_video_id !== null,
            'session_started_at' => $r->session?->started_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
            'stream_url' => $playable
                ? URL::temporarySignedRoute('api.signed.learning.rooms.recordings', now()->addHours(6), ['slug' => $room->slug, 'recording' => $r->id, 'u' => $viewerId])
                : null,
        ];
    }

    /** Like the web payload, but the link opens on the phone without a web session. */
    private function materialRow(LearningRoomMaterial $m): array
    {
        $room = $m->room;

        return [
            'id' => $m->id,
            'title' => $m->title,
            'name' => $m->original_name,
            'size' => (int) $m->size_bytes,
            'created_at' => $m->created_at?->toIso8601String(),
            'url' => URL::temporarySignedRoute('api.signed.learning.rooms.materials', now()->addHours(6), [
                'slug' => $room->slug, 'material' => $m->id, 'u' => request()->user()->id,
            ]),
        ];
    }

    private function owns(LearningRoom $room, LearningRoomRecording $recording): void
    {
        abort_unless((int) $recording->learning_room_id === (int) $room->id, 404);
    }

    private function can(Request $request, string $ability, LearningRoom $room): void
    {
        abort_unless(Gate::forUser($request->user())->allows($ability, $room), 403);
    }
}
