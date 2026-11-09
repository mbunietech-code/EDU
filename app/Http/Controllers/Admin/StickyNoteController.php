<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\StickyNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The sticky-note board on the admin dashboard. Every admin and helpdesk
 * member (DMO…) can read and add notes; the author or a super admin can
 * change, pin or remove one. Not for passwords: those go in the vault.
 */
class StickyNoteController extends Controller
{
    /** The notes page (opened from the note icon in the top bar). */
    public function index(Request $request): View
    {
        $notes = StickyNote::with(['creator', 'editor'])->orderByDesc('pinned')->latest('updated_at')->get();

        // Clears the "new notes" dot on the top-bar icon.
        StickyNote::markSeen($request->user());

        return view('admin.sticky-notes.index', [
            'pinned' => $notes->where('pinned', true)->values(),
            'others' => $notes->where('pinned', false)->values(),
            'total' => $notes->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $note = StickyNote::create($data + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        ActivityLog::log('sticky_note_added', 'StickyNote', $note->id);

        return back()->with('success', 'Note added.')->with('new_note', $note->id);
    }

    public function update(Request $request, StickyNote $note): RedirectResponse
    {
        abort_unless($note->canBeChangedBy($request->user()), 403);

        $note->update($this->validated($request) + ['updated_by' => $request->user()->id]);
        ActivityLog::log('sticky_note_updated', 'StickyNote', $note->id);

        return back()->with('success', 'Note saved.');
    }

    public function pin(Request $request, StickyNote $note): RedirectResponse
    {
        abort_unless($note->canBeChangedBy($request->user()), 403);

        $note->update(['pinned' => ! $note->pinned, 'updated_by' => $request->user()->id]);

        return back();
    }

    public function destroy(Request $request, StickyNote $note): RedirectResponse
    {
        abort_unless($note->canBeChangedBy($request->user()), 403);

        ActivityLog::log('sticky_note_deleted', 'StickyNote', $note->id, ['body' => mb_substr($note->body, 0, 200)]);
        $note->delete();

        return back()->with('success', 'Note removed.');
    }

    /** @return array{body:string,color:string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'color' => ['required', Rule::in(array_keys(StickyNote::COLORS))],
        ]);
    }
}
