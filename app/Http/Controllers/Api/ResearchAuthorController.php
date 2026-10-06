<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Research;
use App\Models\ResearchCategory;
use App\Models\ResearchChapter;
use App\Models\ResearchSection;
use App\Services\ResearchImporter;
use App\Services\ResearchWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Write research from the app: details, chapters, sections, import and
 * submit for review. Same rules as Research\ContributorController.
 */
class ResearchAuthorController extends Controller
{
    public function categories(Request $request): JsonResponse
    {
        $this->writer($request);

        return response()->json(['data' => ResearchCategory::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->writer($request);

        $research = Research::create($this->details($request) + [
            'user_id' => $request->user()->id,
            'status' => 'draft',
        ]);

        return response()->json(['data' => $this->outline($research), 'message' => 'Research created. Add your chapters and sections.'], 201);
    }

    public function show(Request $request, Research $research): JsonResponse
    {
        $this->owner($request, $research);

        return response()->json(['data' => $this->outline($research)]);
    }

    public function update(Request $request, Research $research): JsonResponse
    {
        $this->editable($request, $research);
        $research->update($this->details($request));

        return response()->json(['data' => $this->outline($research), 'message' => 'Details saved.']);
    }

    public function destroy(Request $request, Research $research): JsonResponse
    {
        $this->owner($request, $research);
        abort_unless(in_array($research->status, ['draft', 'changes_requested', 'archived'], true), 403, 'Only drafts can be deleted.');

        $research->delete();

        return response()->json(['message' => 'Research deleted.']);
    }

    public function submit(Request $request, Research $research, ResearchWorkflow $workflow): JsonResponse
    {
        $this->owner($request, $research);

        if (! $research->canBeSubmitted()) {
            return response()->json(['message' => 'Add at least one chapter with a section before submitting.'], 422);
        }

        $workflow->submit($research);

        return response()->json(['data' => $this->outline($research->fresh()), 'message' => 'Submitted for review. You will be notified of the outcome.']);
    }

    public function import(Request $request, Research $research, ResearchImporter $importer): JsonResponse
    {
        $this->editable($request, $research);

        $request->validate([
            'document' => ['nullable', 'file', 'mimes:docx,md,markdown,txt,text', 'max:15360'],
            'text' => ['nullable', 'string', 'max:400000'],
        ]);

        if (! $request->hasFile('document') && blank($request->input('text'))) {
            return response()->json(['message' => 'Attach a document or paste some text first.'], 422);
        }

        try {
            $result = $importer->import($research, $request->file('document'), $request->input('text'));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not import: '.$e->getMessage()], 422);
        }

        if ($result['chapters'] === 0) {
            return response()->json(['message' => 'Nothing recognisable was found. Use "# Heading" for chapters and "## Heading" for sections.'], 422);
        }

        ActivityLog::log('research_imported', 'Research', $research->id, $result + ['via' => 'api']);

        return response()->json([
            'data' => $this->outline($research->fresh()),
            'message' => "Imported {$result['chapters']} chapter(s) and {$result['sections']} section(s).",
        ]);
    }

    // --- Chapters ------------------------------------------------------------

    public function storeChapter(Request $request, Research $research): JsonResponse
    {
        $this->editable($request, $research);
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $research->chapters()->create([
            'title' => $data['title'],
            'position' => (int) $research->chapters()->max('position') + 1,
        ]);

        return response()->json(['data' => $this->outline($research), 'message' => 'Chapter added.'], 201);
    }

    public function updateChapter(Request $request, ResearchChapter $chapter): JsonResponse
    {
        $this->editable($request, $chapter->research);
        $chapter->update($request->validate(['title' => ['required', 'string', 'max:255']]));

        return response()->json(['data' => $this->outline($chapter->research), 'message' => 'Chapter renamed.']);
    }

    public function destroyChapter(Request $request, ResearchChapter $chapter): JsonResponse
    {
        $research = $chapter->research;
        $this->editable($request, $research);
        $chapter->delete();

        return response()->json(['data' => $this->outline($research), 'message' => 'Chapter removed.']);
    }

    // --- Sections ------------------------------------------------------------

    public function storeSection(Request $request, ResearchChapter $chapter): JsonResponse
    {
        $this->editable($request, $chapter->research);
        $data = $this->sectionData($request);

        $section = $chapter->sections()->create($data + [
            'position' => (int) $chapter->sections()->max('position') + 1,
        ]);

        return response()->json(['data' => $this->sectionRow($section, true), 'message' => 'Section added.'], 201);
    }

    public function showSection(Request $request, ResearchSection $section): JsonResponse
    {
        $this->owner($request, $section->chapter->research);

        return response()->json(['data' => $this->sectionRow($section, true)]);
    }

    public function updateSection(Request $request, ResearchSection $section): JsonResponse
    {
        $this->editable($request, $section->chapter->research);
        $section->update($this->sectionData($request));

        return response()->json(['data' => $this->sectionRow($section, true), 'message' => 'Section saved.']);
    }

    public function destroySection(Request $request, ResearchSection $section): JsonResponse
    {
        $research = $section->chapter->research;
        $this->editable($request, $research);
        $section->delete();

        return response()->json(['data' => $this->outline($research), 'message' => 'Section removed.']);
    }

    /** Only this research's own chapters / sections can be reordered. */
    public function reorder(Request $request, Research $research): JsonResponse
    {
        $this->editable($request, $research);

        $data = $request->validate([
            'type' => ['required', Rule::in(['chapter', 'section'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $query = $data['type'] === 'chapter'
            ? ResearchChapter::where('research_id', $research->id)
            : ResearchSection::whereIn('research_chapter_id', $research->chapters()->select('id'));

        foreach (array_values($data['ids']) as $position => $id) {
            (clone $query)->whereKey($id)->update(['position' => $position + 1]);
        }

        return response()->json(['data' => $this->outline($research)]);
    }

    // --- Helpers -------------------------------------------------------------

    protected function details(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'research_category_id' => ['nullable', 'exists:research_categories,id'],
            'summary' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    protected function sectionData(Request $request): array
    {
        return $request->validate([
            'heading' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:200000'],
        ]);
    }

    protected function writer(Request $request): void
    {
        abort_unless($request->user()->canWriteResearch(), 403, 'Research writing is not enabled for your account.');
    }

    protected function owner(Request $request, Research $research): void
    {
        $this->writer($request);
        abort_unless((int) $research->user_id === (int) $request->user()->id || $request->user()->is_admin, 404);
    }

    protected function editable(Request $request, Research $research): void
    {
        $this->owner($request, $research);
        abort_unless($research->isEditableBy($request->user()), 403, 'This research is queued for review and cannot be edited right now.');
    }

    protected function outline(Research $research): array
    {
        $research->load(['category:id,name', 'chapters' => fn ($q) => $q->orderBy('position'), 'chapters.sections' => fn ($q) => $q->orderBy('position')]);

        return [
            'id' => $research->id,
            'slug' => $research->slug,
            'title' => $research->title,
            'summary' => $research->summary,
            'category_id' => $research->research_category_id,
            'category' => $research->category?->name,
            'status' => $research->status,
            'status_label' => $research->statusLabel(),
            'review_note' => $research->status === 'changes_requested' ? $research->review_note : null,
            'can_edit' => $research->isEditableBy(request()->user()),
            'can_submit' => $research->canBeSubmitted(),
            'chapters' => $research->chapters->map(fn (ResearchChapter $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'sections' => $c->sections->map(fn (ResearchSection $s) => $this->sectionRow($s))->values(),
            ])->values(),
        ];
    }

    protected function sectionRow(ResearchSection $s, bool $withBody = false): array
    {
        $words = str_word_count(strip_tags((string) $s->body));

        return [
            'id' => $s->id,
            'chapter_id' => $s->research_chapter_id,
            'heading' => $s->heading,
            'words' => $words,
        ] + ($withBody ? ['body' => (string) $s->body] : []);
    }
}
