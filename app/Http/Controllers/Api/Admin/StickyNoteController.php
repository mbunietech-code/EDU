<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\StickyNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Team notes for the MHub app: the same board as admin/sticky-notes.
 * Everyone reads and adds; the author or a super admin changes, pins or
 * removes a note. Not for passwords: those go in the vault.
 */
class StickyNoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $unseen = StickyNote::unseenCountFor($user);
        $notes = StickyNote::with(['creator:id,name', 'editor:id,name'])->orderByDesc('pinned')->latest('updated_at')->get();

        // Opening the board clears the "new notes" dot, as on the web.
        StickyNote::markSeen($user);

        return response()->json([
            'data' => $notes->map(fn (StickyNote $n) => $this->row($n, $user))->values(),
            'meta' => [
                'unseen' => $unseen,
                'colors' => collect(StickyNote::COLORS)->map(fn ($c) => ['background' => $c[0], 'border' => $c[1]]),
            ],
        ]);
    }

    /** Notes others added or changed since this admin last opened the board. */
    public function unseen(Request $request): JsonResponse
    {
        return response()->json(['unseen' => StickyNote::unseenCountFor($request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $note = StickyNote::create($request->validate(StickyNote::rules()) + ['created_by' => $user->id, 'updated_by' => $user->id]);
        ActivityLog::log('sticky_note_added', 'StickyNote', $note->id);

        return response()->json(['message' => 'Note added.', 'data' => $this->row($note->load(['creator:id,name', 'editor:id,name']), $user)], 201);
    }

    public function update(Request $request, StickyNote $note): JsonResponse
    {
        $user = $request->user();
        abort_unless($note->canBeChangedBy($user), 403);

        $note->update($request->validate(StickyNote::rules()) + ['updated_by' => $user->id]);
        ActivityLog::log('sticky_note_updated', 'StickyNote', $note->id);

        return response()->json(['message' => 'Note saved.']);
    }

    public function pin(Request $request, StickyNote $note): JsonResponse
    {
        $user = $request->user();
        abort_unless($note->canBeChangedBy($user), 403);

        $note->update(['pinned' => ! $note->pinned, 'updated_by' => $user->id]);

        return response()->json(['message' => $note->pinned ? 'Note pinned.' : 'Note unpinned.', 'pinned' => $note->pinned]);
    }

    public function destroy(Request $request, StickyNote $note): JsonResponse
    {
        abort_unless($note->canBeChangedBy($request->user()), 403);

        ActivityLog::log('sticky_note_deleted', 'StickyNote', $note->id, ['body' => mb_substr($note->body, 0, 200)]);
        $note->delete();

        return response()->json(['message' => 'Note removed.']);
    }

    private function row(StickyNote $n, $user): array
    {
        [$bg, $border] = $n->palette();

        return [
            'id' => $n->id,
            'body' => $n->body,
            'color' => $n->color,
            'background' => $bg,
            'border' => $border,
            'pinned' => (bool) $n->pinned,
            'creator_name' => $n->creator?->name,
            'editor_name' => $n->editor?->name,
            'edited' => $n->updated_by !== null && (int) $n->updated_by !== (int) $n->created_by,
            'can_change' => $n->canBeChangedBy($user),
            'created_at' => optional($n->created_at)->toIso8601String(),
            'updated_ago' => optional($n->updated_at)->diffForHumans(),
        ];
    }
}
