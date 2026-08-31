<?php

namespace App\Http\Controllers\Research;

use App\Http\Controllers\Controller;
use App\Models\Research;
use App\Models\ResearchCategory;
use App\Models\ResearchChapter;
use App\Models\ResearchSection;
use App\Services\ResearchImporter;
use App\Services\ResearchWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContributorController extends Controller
{
    private function mine(): \Illuminate\Database\Eloquent\Builder
    {
        return Research::where('user_id', auth()->id());
    }

    private function authorize_(Research $research): void
    {
        abort_unless(
            $research->user_id === auth()->id() || auth()->user()->is_admin,
            403,
        );
    }

    private function assertEditable(Research $research): void
    {
        abort_unless(
            $research->isEditableBy(auth()->user()),
            403,
            'This research is queued for review and cannot be edited right now.',
        );
    }

    public function index()
    {
        $researches = $this->mine()
            ->with('category')
            ->withCount('chapters')
            ->latest()->get();

        return view('research.contributor.index', compact('researches'));
    }

    public function create()
    {
        $categories = ResearchCategory::orderBy('name')->get();

        return view('research.contributor.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'research_category_id' => ['nullable', 'exists:research_categories,id'],
            'summary' => ['nullable', 'string', 'max:1000'],
        ]);

        $research = Research::create([
            ...$data,
            'user_id' => auth()->id(),
            'status' => 'draft',
        ]);

        return redirect()->route('research.contributor.edit', $research)
            ->with('success', 'Research created. Add your chapters and sections.');
    }

    /** The builder: chapters + sections outline. */
    public function edit(Research $research)
    {
        $this->authorize_($research);
        $research->load('chapters.sections', 'category', 'reviews.reviewer');
        $categories = ResearchCategory::orderBy('name')->get();

        return view('research.contributor.edit', compact('research', 'categories'));
    }

    public function update(Request $request, Research $research)
    {
        $this->authorize_($research);
        $this->assertEditable($research);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'research_category_id' => ['nullable', 'exists:research_categories,id'],
            'summary' => ['nullable', 'string', 'max:1000'],
        ]);

        $research->update($data);

        return back()->with('success', 'Details saved.');
    }

    public function destroy(Research $research)
    {
        $this->authorize_($research);
        abort_unless(in_array($research->status, ['draft', 'changes_requested', 'archived'], true), 403);

        $research->delete();

        return redirect()->route('research.contributor.index')->with('success', 'Research deleted.');
    }

    public function submit(Research $research, ResearchWorkflow $workflow)
    {
        $this->authorize_($research);

        if (! $research->canBeSubmitted()) {
            return back()->with('error', 'Add at least one chapter with a section before submitting.');
        }

        $workflow->submit($research);

        return back()->with('success', 'Submitted for review. You will be notified of the outcome.');
    }

    public function import(Request $request, Research $research, ResearchImporter $importer)
    {
        $this->authorize_($research);
        $this->assertEditable($research);

        $request->validate([
            'document' => ['nullable', 'file', 'mimes:docx,md,markdown,txt,text', 'max:15360'],
            'text' => ['nullable', 'string', 'max:400000'],
        ]);

        if (! $request->hasFile('document') && blank($request->input('text'))) {
            return back()->with('error', 'Attach a document or paste some text first.');
        }

        try {
            $result = $importer->import(
                $research,
                $request->file('document'),
                $request->input('text'),
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not import: '.$e->getMessage());
        }

        if ($result['chapters'] === 0) {
            return back()->with('error', 'Nothing recognisable was found. Use "# Heading" for chapters and "## Heading" for sections.');
        }

        \App\Models\ActivityLog::log('research_imported', 'Research', $research->id, $result);

        return back()->with('success', "Imported {$result['chapters']} chapter(s) and {$result['sections']} section(s). Review and edit as needed.");
    }

    // --- Chapters ------------------------------------------------------
    public function storeChapter(Request $request, Research $research)
    {
        $this->authorize_($research);
        $this->assertEditable($research);

        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $research->chapters()->create([
            'title' => $data['title'],
            'position' => (int) $research->chapters()->max('position') + 1,
        ]);

        return back()->with('success', 'Chapter added.');
    }

    public function updateChapter(Request $request, ResearchChapter $chapter)
    {
        $this->authorize_($chapter->research);
        $this->assertEditable($chapter->research);

        $chapter->update($request->validate(['title' => ['required', 'string', 'max:255']]));

        return back()->with('success', 'Chapter renamed.');
    }

    public function destroyChapter(ResearchChapter $chapter)
    {
        $this->authorize_($chapter->research);
        $this->assertEditable($chapter->research);

        $chapter->delete();

        return back()->with('success', 'Chapter removed.');
    }

    // --- Sections -----------------------------------------------------
    public function editSection(ResearchChapter $chapter, ResearchSection $section)
    {
        $this->authorize_($chapter->research);
        abort_unless($section->research_chapter_id === $chapter->id, 404);

        $research = $chapter->research;

        return view('research.contributor.section', compact('research', 'chapter', 'section'));
    }

    public function createSection(ResearchChapter $chapter)
    {
        $this->authorize_($chapter->research);
        $this->assertEditable($chapter->research);

        $section = $chapter->sections()->create([
            'heading' => 'Untitled section',
            'position' => (int) $chapter->sections()->max('position') + 1,
        ]);

        return redirect()->route('research.contributor.section.edit', [$chapter, $section]);
    }

    public function storeSection(Request $request, ResearchChapter $chapter, ResearchSection $section)
    {
        $this->authorize_($chapter->research);
        abort_unless($section->research_chapter_id === $chapter->id, 404);
        $this->assertEditable($chapter->research);

        $section->update($request->validate([
            'heading' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:200000'],
        ]));

        return redirect()->route('research.contributor.edit', $chapter->research)
            ->with('success', 'Section saved.');
    }

    public function destroySection(ResearchChapter $chapter, ResearchSection $section)
    {
        $this->authorize_($chapter->research);
        abort_unless($section->research_chapter_id === $chapter->id, 404);
        $this->assertEditable($chapter->research);

        $section->delete();

        return redirect()->route('research.contributor.edit', $chapter->research)
            ->with('success', 'Section removed.');
    }

    public function reorder(Request $request, Research $research)
    {
        $this->authorize_($research);
        $this->assertEditable($research);

        $data = $request->validate([
            'type' => ['required', Rule::in(['chapter', 'section'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $model = $data['type'] === 'chapter' ? ResearchChapter::class : ResearchSection::class;
        foreach (array_values($data['ids']) as $pos => $id) {
            $model::where('id', $id)->update(['position' => $pos + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
