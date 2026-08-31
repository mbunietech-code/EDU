<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Research;
use App\Models\ResearchCategory;
use App\Models\ResearchChapter;
use App\Models\ResearchReadingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = ResearchCategory::query()
            ->withCount(['researches as published_count' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('position')->orderBy('name')->get()
            ->filter(fn ($c) => $c->published_count > 0)
            ->map(fn ($c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'description' => $c->description,
                'count' => $c->published_count,
            ])->values();

        $recent = Research::published()->with(['category:id,name', 'author:id,name'])
            ->latest('published_at')->take(10)->get()
            ->map(fn (Research $r) => $this->card($r));

        $continue = ResearchReadingProgress::where('user_id', $request->user()->id)
            ->whereHas('research', fn ($q) => $q->where('status', 'published'))
            ->with('research.category:id,name')
            ->latest('last_read_at')->take(5)->get()
            ->map(fn ($p) => array_merge($this->card($p->research), ['percent' => $p->percent]));

        return response()->json([
            'categories' => $categories,
            'recent' => $recent,
            'continue' => $continue,
        ]);
    }

    public function category(Request $request, string $slug): JsonResponse
    {
        $category = ResearchCategory::where('slug', $slug)->firstOrFail();

        $items = $category->researches()->where('status', 'published')
            ->with('author:id,name')
            ->latest('published_at')->get()
            ->map(fn (Research $r) => $this->card($r));

        return response()->json([
            'category' => ['slug' => $category->slug, 'name' => $category->name, 'description' => $category->description],
            'data' => $items,
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $research = Research::published()->where('slug', $slug)
            ->with(['category:id,name', 'author:id,name', 'chapters.sections:id,research_chapter_id,heading,position'])
            ->firstOrFail();

        $research->increment('views');

        $progress = ResearchReadingProgress::firstWhere([
            'user_id' => $request->user()->id, 'research_id' => $research->id,
        ]);

        return response()->json(['data' => [
            'slug' => $research->slug,
            'title' => $research->title,
            'summary' => $research->summary,
            'category' => $research->category->name ?? null,
            'author' => $research->author->name ?? 'Contributor',
            'published_at' => optional($research->published_at)->toDateString(),
            'views' => $research->views,
            'percent' => $progress->percent ?? 0,
            'chapters' => $research->chapters->map(fn ($ch) => [
                'id' => $ch->id,
                'title' => $ch->title,
                'sections' => $ch->sections->map(fn ($s) => [
                    'id' => $s->id, 'heading' => $s->heading,
                ])->all(),
            ])->all(),
        ]]);
    }

    public function chapter(Request $request, string $slug, ResearchChapter $chapter): JsonResponse
    {
        $research = Research::published()->where('slug', $slug)->firstOrFail();
        abort_unless($chapter->research_id === $research->id, 404);

        $research->load('chapters:id,research_id,title,position');
        $chapter->load('sections');

        $chapters = $research->chapters;
        $idx = $chapters->search(fn ($c) => $c->id === $chapter->id);

        $progress = ResearchReadingProgress::firstOrCreate(
            ['user_id' => $request->user()->id, 'research_id' => $research->id],
            ['last_read_at' => now()],
        );
        $progress->forceFill([
            'last_section_id' => $chapter->sections->first()?->id,
            'last_read_at' => now(),
        ])->save();

        return response()->json(['data' => [
            'research_title' => $research->title,
            'chapter' => [
                'id' => $chapter->id,
                'title' => $chapter->title,
                'sections' => $chapter->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'heading' => $s->heading,
                    'body' => $s->body ?? '',
                ])->all(),
            ],
            'prev' => $idx > 0 ? ['id' => $chapters[$idx - 1]->id, 'title' => $chapters[$idx - 1]->title] : null,
            'next' => $idx < $chapters->count() - 1 ? ['id' => $chapters[$idx + 1]->id, 'title' => $chapters[$idx + 1]->title] : null,
            'done_section_ids' => $progress->done_section_ids ?? [],
        ]]);
    }

    public function markSection(Request $request, string $slug): JsonResponse
    {
        $research = Research::published()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'done' => ['required', 'boolean'],
        ]);
        $sectionId = (int) $data['section_id'];

        $progress = ResearchReadingProgress::firstOrNew([
            'user_id' => $request->user()->id, 'research_id' => $research->id,
        ]);

        $done = collect($progress->done_section_ids ?? [])->map(fn ($id) => (int) $id);
        $done = $data['done']
            ? $done->push($sectionId)->unique()->values()
            : $done->reject(fn ($id) => $id === $sectionId)->values();

        $total = max(1, $research->sectionsCount());
        $progress->fill([
            'done_section_ids' => $done->all(),
            'last_section_id' => $data['section_id'],
            'percent' => min(100, (int) round($done->count() / $total * 100)),
            'last_read_at' => now(),
        ])->save();

        return response()->json(['data' => ['percent' => $progress->percent, 'done_section_ids' => $done->all()]]);
    }

    /** My submissions (contributors track status from the app; editing stays on web). */
    public function mine(Request $request): JsonResponse
    {
        abort_unless($request->user()->canWriteResearch(), 403);

        $items = Research::where('user_id', $request->user()->id)
            ->with('category:id,name')->withCount('chapters')
            ->latest()->get()
            ->map(fn (Research $r) => [
                'slug' => $r->slug,
                'title' => $r->title,
                'category' => $r->category->name ?? null,
                'status' => $r->status,
                'status_label' => $r->statusLabel(),
                'chapters_count' => $r->chapters_count,
                'review_note' => $r->status === 'changes_requested' ? $r->review_note : null,
                'updated_ago' => $r->updated_at->diffForHumans(),
            ]);

        return response()->json(['data' => $items]);
    }

    private function card(Research $r): array
    {
        return [
            'slug' => $r->slug,
            'title' => $r->title,
            'summary' => $r->summary,
            'category' => $r->category->name ?? null,
            'author' => $r->author->name ?? 'Contributor',
            'published_ago' => optional($r->published_at)->diffForHumans(),
        ];
    }
}
