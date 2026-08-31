<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Research;
use App\Models\ResearchCategory;
use App\Models\ResearchChapter;
use App\Services\DeletionService;
use App\Services\ResearchWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResearchController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('status', 'review');

        $query = Research::with(['category', 'author:id,name', 'reviewer:id,name'])
            ->withCount('chapters')
            ->latest();

        $query = match ($tab) {
            'review' => $query->inReview(),
            'published' => $query->where('status', 'published'),
            'drafts' => $query->where('status', 'draft'),
            'archived' => $query->where('status', 'archived'),
            default => $query,
        };

        $researches = $query->paginate(20)->withQueryString();
        $reviewCount = Research::pendingReviewCount();

        return view('admin.research.index', compact('researches', 'tab', 'reviewCount'));
    }

    public function show(Research $research, ResearchWorkflow $workflow)
    {
        $research->load(['category', 'author:id,name,email', 'chapters.sections', 'reviews.reviewer:id,name']);

        if ($research->status === 'submitted') {
            $workflow->startReview($research, auth()->user());
        }

        return view('admin.research.show', compact('research'));
    }

    public function readChapter(Research $research, ResearchChapter $chapter)
    {
        abort_unless($chapter->research_id === $research->id, 404);
        $research->load('chapters.sections');
        $chapter->load('sections');

        return view('admin.research.read', compact('research', 'chapter'));
    }

    public function approve(Request $request, Research $research, ResearchWorkflow $workflow)
    {
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;
        $workflow->approveAndPublish($research, auth()->user(), $comment);

        return redirect()->route('admin.research.index')->with('success', 'Research approved & published.');
    }

    public function requestChanges(Request $request, Research $research, ResearchWorkflow $workflow)
    {
        $comment = $request->validate(['comment' => ['required', 'string', 'max:2000']])['comment'];
        $workflow->requestChanges($research, auth()->user(), $comment);

        return redirect()->route('admin.research.index')->with('success', 'Changes requested — the author has been notified.');
    }

    public function reject(Request $request, Research $research, ResearchWorkflow $workflow)
    {
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;
        $workflow->reject($research, auth()->user(), $comment);

        return redirect()->route('admin.research.index')->with('success', 'Research rejected.');
    }

    public function unpublish(Research $research, ResearchWorkflow $workflow)
    {
        $workflow->unpublish($research, auth()->user());

        return back()->with('success', 'Research unpublished.');
    }

    public function destroy(Request $request, Research $research, DeletionService $deletions)
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];
        $deletions->delete($research, $reason);

        return redirect()->route('admin.research.index')->with('success', 'Research deleted.');
    }

    // --- Categories -------------------------------------------------
    public function categories()
    {
        $categories = ResearchCategory::withCount('researches')->orderBy('position')->orderBy('name')->get();

        return view('admin.research.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:60'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $category = ResearchCategory::create($data);
        ActivityLog::log('research_category_created', 'ResearchCategory', $category->id, ['name' => $category->name]);

        return back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, ResearchCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('research_categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:60'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(ResearchCategory $category)
    {
        $category->researches()->update(['research_category_id' => null]);
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
