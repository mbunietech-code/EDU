<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\StickyNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The sticky-note board on the admin dashboard. Every admin and helpdesk
 * member (DMO…) can read and add notes; the author or a super admin can
 * change, pin or remove one. Not for passwords: those go in the vault.
 */
class StickyNoteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $note = StickyNote::create($data + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        ActivityLog::log('sticky_note_added', 'StickyNote', $note->id);

        return back()->with('success', 'Note added.')->withFragment('notes');
    }

    public function update(Request $request, StickyNote $note): RedirectResponse
    {
        abort_unless($note->canBeChangedBy($request->user()), 403);

        $note->update($this->validated($request) + ['updated_by' => $request->user()->id]);
        ActivityLog::log('sticky_note_updated', 'StickyNote', $note->id);

        return back()->with('success', 'Note saved.')->withFragment('notes');
    }

    public function pin(Request $request, StickyNote $note): RedirectResponse
    {
        abort_unless($note->canBeChangedBy($request->user()), 403);

        $note->update(['pinned' => ! $note->pinned, 'updated_by' => $request->user()->id]);

        return back()->withFragment('notes');
    }

    public function destroy(Request $request, StickyNote $note): RedirectResponse
    {
        abort_unless($note->canBeChangedBy($request->user()), 403);

        ActivityLog::log('sticky_note_deleted', 'StickyNote', $note->id, ['body' => mb_substr($note->body, 0, 200)]);
        $note->delete();

        return back()->with('success', 'Note removed.')->withFragment('notes');
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
