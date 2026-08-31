<?php

namespace App\Http\Controllers\Research;

use App\Http\Controllers\Controller;
use App\Models\Research;
use App\Models\ResearchChapter;
use App\Models\ResearchReadingProgress;
use Illuminate\Http\Request;

/**
 * The signed-in reading experience. The public teaser listing lives in
 * App\Http\Controllers\Public\ResearchController.
 */
class LibraryController extends Controller
{
    public function read(Research $research, ResearchChapter $chapter)
    {
        abort_unless($research->isPublished(), 404);
        abort_unless($chapter->research_id === $research->id, 404);

        $research->load(['category', 'chapters.sections:id,research_chapter_id,heading,position']);
        $chapter->load('sections');

        $orderedChapters = $research->chapters;
        $idx = $orderedChapters->search(fn ($c) => $c->id === $chapter->id);
        $prev = $idx > 0 ? $orderedChapters[$idx - 1] : null;
        $next = $idx < $orderedChapters->count() - 1 ? $orderedChapters[$idx + 1] : null;

        $this->touchProgress($research, $chapter);

        $progress = ResearchReadingProgress::firstWhere([
            'user_id' => auth()->id(), 'research_id' => $research->id,
        ]);

        return view('research.read', compact('research', 'chapter', 'prev', 'next', 'progress'));
    }

    public function markSection(Request $request, Research $research)
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'done' => ['required', 'boolean'],
        ]);

        $progress = ResearchReadingProgress::firstOrNew([
            'user_id' => auth()->id(), 'research_id' => $research->id,
        ]);

        $done = collect($progress->done_section_ids ?? [])->map(fn ($id) => (int) $id);
        $done = $data['done']
            ? $done->push((int) $data['section_id'])->unique()->values()
            : $done->reject(fn ($id) => (int) $id === (int) $data['section_id'])->values();

        $total = max(1, $research->sectionsCount());
        $progress->fill([
            'done_section_ids' => $done->all(),
            'last_section_id' => $data['section_id'],
            'percent' => min(100, (int) round($done->count() / $total * 100)),
            'last_read_at' => now(),
        ])->save();

        return response()->json(['percent' => $progress->percent, 'done' => $done->all()]);
    }

    private function touchProgress(Research $research, ResearchChapter $chapter): void
    {
        $first = $chapter->sections->first();

        ResearchReadingProgress::updateOrCreate(
            ['user_id' => auth()->id(), 'research_id' => $research->id],
            ['last_section_id' => $first?->id, 'last_read_at' => now()],
        );
    }
}
