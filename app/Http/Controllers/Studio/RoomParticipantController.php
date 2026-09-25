<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMember;
use App\Models\User;
use App\Services\Learning\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Invited members and live participants of a room.
 */
class RoomParticipantController extends Controller
{
    private const MEMBERS_PER_PAGE = 50;

    public function __construct(protected RoomService $rooms)
    {
    }

    public function index(Request $request, LearningRoom $room): View
    {
        $this->authorize('viewAttendance', $room);
        $viewer = $request->user();

        $room->load('host:id,name')->loadCount('members');

        $members = $room->members()
            ->with(['user:id,name,email,status', 'adder:id,name'])
            ->latest('id')
            ->paginate(self::MEMBERS_PER_PAGE, ['*'], 'members_page')
            ->withQueryString();

        $present = $room->isLive() ? $this->rooms->presentParticipants($room) : collect();

        // Summary of the running session, or of the last one.
        $session = $room->sessions()->withCount('attendances')->first();
        $summary = null;
        if ($session) {
            $rows = LearningRoomAttendance::query()->where('learning_room_session_id', $session->id);
            $summary = [
                'session' => $session,
                'attendees' => (int) $session->attendances_count,
                'removed' => (clone $rows)->whereNotNull('removed_at')->count(),
                'total_seconds' => (int) (clone $rows)->sum('total_seconds'),
                'present' => $present->count(),
            ];
        }

        return view('studio.rooms.participants', [
            'room' => $room,
            'canManage' => $viewer->can('manage', $room),
            'showEmail' => (bool) $viewer->is_admin,
            'members' => $members,
            'present' => $present,
            'summary' => $summary,
            'memberPicker' => [
                'searchUrl' => route('studio.users.search'),
                'name' => 'user_ids[]',
                'selected' => [],
                'multiple' => true,
                'excludeIds' => array_values(array_filter([$room->host_id])),
                'inputId' => 'add-members',
                'label' => 'Search members to invite',
            ],
        ]);
    }

    public function storeMembers(Request $request, LearningRoom $room): RedirectResponse
    {
        $this->authorize('manage', $room);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:200'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('status', 'active')],
        ], [
            'user_ids.required' => 'Pick at least one member to invite.',
            'user_ids.min' => 'Pick at least one member to invite.',
            'user_ids.*.exists' => 'Only active members can be invited.',
        ]);

        if ($room->access !== 'private') {
            throw ValidationException::withMessages([
                'user_ids' => 'Invitations only apply to private rooms. Change the room to “Invited members only” first.',
            ]);
        }

        $actor = $request->user();
        $added = 0;

        foreach (array_unique(array_map('intval', $data['user_ids'])) as $userId) {
            if ($room->host_id !== null && $userId === (int) $room->host_id) {
                continue; // the host is always in
            }

            $member = LearningRoomMember::query()->firstOrCreate(
                ['learning_room_id' => $room->id, 'user_id' => $userId],
                ['added_by' => $actor->id],
            );

            if ($member->wasRecentlyCreated) {
                $added++;
            }
        }

        if ($added > 0) {
            ActivityLog::log('learning_room_members_added', 'LearningRoom', $room->id, [
                'title' => $room->title,
                'added' => $added,
            ]);
        }

        return back()->with('success', $added > 0
            ? $added.' '.($added === 1 ? 'member' : 'members').' invited.'
            : 'Those members were already invited.');
    }

    public function destroyMember(Request $request, LearningRoom $room, LearningRoomMember $member): RedirectResponse
    {
        $this->authorize('manage', $room);

        $name = $member->user?->name ?? 'The member';
        $userId = $member->user_id;
        $member->delete();

        ActivityLog::log('learning_room_member_removed', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'user_id' => $userId,
        ]);

        return back()->with('success', $name.' is no longer invited.');
    }

    public function remove(Request $request, LearningRoom $room, User $user): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        try {
            // Also disconnects them from the self-hosted SFU.
            $this->rooms->removeParticipant($room, $user, $request->user());
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()->with('error', collect($e->errors())->flatten()->first() ?? 'The participant could not be removed.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'removed' => true,
                'user_id' => $user->id,
            ]);
        }

        return back()->with('success', $user->name.' was removed from this session and cannot rejoin it.');
    }
}
