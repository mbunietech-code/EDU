<?php

namespace App\Http\Controllers\Admin\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMessage;
use App\Models\LearningRoomSession;
use App\Services\Learning\LiveProvider;
use App\Services\Learning\LiveServerClient;
use App\Services\Learning\RoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Learning → Live sessions: what is live right now on our
 * self-hosted video server, who is in each room, chat moderation, and a
 * reachability check of the SFU. Changing things (end, remove, lock, rights)
 * uses the same studio endpoints as the host, so the same rules apply.
 */
class LiveSessionController extends Controller
{
    public function __construct(protected RoomService $rooms, protected LiveProvider $live)
    {
    }

    public function index(Request $request, LiveServerClient $server): View
    {
        Gate::authorize('rooms.view');

        $live = LearningRoom::query()
            ->live()
            ->with(['host:id,name', 'category:id,name'])
            ->orderByDesc('started_at')
            ->get();

        $sessions = LearningRoomSession::query()
            ->whereIn('learning_room_id', $live->pluck('id'))
            ->whereNull('ended_at')
            ->withCount(['attendances as present_count' => fn ($q) => $q->present()])
            ->get()
            ->keyBy('learning_room_id');

        $recent = LearningRoomSession::query()
            ->whereNotNull('ended_at')
            ->with(['room' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'host_id', 'deleted_at'])->with('host:id,name')])
            ->withCount('attendances')
            ->orderByDesc('ended_at')
            ->limit(10)
            ->get();

        // The SFU is only contacted when an admin asks for it (it may be slow or down).
        $check = null;
        if ($request->boolean('check')) {
            $reachable = $server->ping();
            $check = ['reachable' => $reachable, 'error' => $reachable ? null : $server->lastError()];
        }

        return view('admin.learning.live.index', [
            'live' => $live,
            'sessions' => $sessions,
            'recent' => $recent,
            'provider' => $this->live->status(),
            'check' => $check,
        ]);
    }

    public function show(Request $request, LearningRoom $room): View
    {
        Gate::authorize('rooms.view');

        $room->load(['host:id,name,email', 'category:id,name']);
        $session = $room->sessions()->whereNull('ended_at')->first() ?? $room->sessions()->first();

        $attendances = $session
            ? LearningRoomAttendance::query()
                ->where('learning_room_session_id', $session->id)
                ->with(['user:id,name,email,status', 'remover:id,name'])
                ->orderByDesc('last_seen_at')
                ->get()
            : collect();

        $messages = $session
            ? LearningRoomMessage::query()
                ->where('learning_room_session_id', $session->id)
                ->with('user:id,name')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString()
            : null;

        return view('admin.learning.live.show', [
            'room' => $room,
            'session' => $session,
            'attendances' => $attendances,
            'messages' => $messages,
            'canModerate' => $room->isManageableBy($request->user()),
            'rights' => $attendances->mapWithKeys(fn (LearningRoomAttendance $a) => [
                $a->id => $a->user ? $this->rooms->permissionsFor($room, $a->user, $a) : null,
            ]),
        ]);
    }

    /** Chat moderation: hide a message for everyone (kept for the audit trail). */
    public function destroyMessage(Request $request, LearningRoom $room, LearningRoomMessage $message): RedirectResponse
    {
        Gate::authorize('rooms.view');
        $this->authorize('moderate', $room);
        abort_unless((int) $message->learning_room_id === (int) $room->id, 404);

        $message->setRelation('room', $room);
        $this->rooms->deleteMessage($message, $request->user());

        return back()->with('success', 'The message was removed from the class chat.');
    }
}
