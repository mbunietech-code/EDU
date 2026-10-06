<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Research;
use App\Models\ResearchCategory;
use App\Services\DeletionService;
use App\Services\ResearchWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Research review for the app, same workflow as Admin\ResearchController:
 * research.view to read the queue, research.manage to decide.
 */
class ResearchReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->allow($request, 'research.view');

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:review,published,drafts,archived,all'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $tab = $data['status'] ?? 'review';

        $query = Research::with(['category:id,name', 'author:id,name', 'reviewer:id,name'])
            ->withCount('chapters')
            ->latest();

        $query = match ($tab) {
            'review' => $query->inReview(),
            'published' => $query->where('status', 'published'),
            'drafts' => $query->where('status', 'draft'),
            'archived' => $query->where('status', 'archived'),
            default => $query,
        };

        if (($term = trim((string) ($data['q'] ?? ''))) !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('title', 'like', $like)
                ->orWhereHas('author', fn ($a) => $a->where('name', 'like', $like)));
        }

        $page = $query->paginate(20)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (Research $r) => $this->row($r))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'review_count' => Research::pendingReviewCount(),
                'can_manage' => $this->can($request, 'research.manage'),
            ],
        ]);
    }

    /** Opening a submitted paper moves it to "under review", like on the web. */
    public function show(Request $request, Research $research, ResearchWorkflow $workflow): JsonResponse
    {
        $this->allow($request, 'research.view');

        if ($research->status === 'submitted' && $this->can($request, 'research.manage')) {
            $workflow->startReview($research, $request->user());
            $research->refresh();
        }

        $research->load(['category:id,name', 'author:id,name,email', 'reviewer:id,name', 'chapters.sections', 'reviews.reviewer:id,name']);

        return response()->json(['data' => $this->row($research) + [
            'summary' => $research->summary,
            'review_note' => $research->review_note,
            'author_email' => $research->author?->email,
            'submitted_at' => $research->submitted_at?->toIso8601String(),
            'can_manage' => $this->can($request, 'research.manage'),
            'chapters' => $research->chapters->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'sections' => $c->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'heading' => $s->heading,
                    'body' => (string) $s->body,
                ])->values(),
            ])->values(),
            'reviews' => $research->reviews->sortByDesc('id')->map(fn ($v) => [
                'id' => $v->id,
                'action' => $v->action,
                'comment' => $v->comment,
                'reviewer' => $v->reviewer?->name,
                'created_at' => $v->created_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    public function approve(Request $request, Research $research, ResearchWorkflow $workflow): JsonResponse
    {
        $this->allow($request, 'research.manage');
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;
        $workflow->approveAndPublish($research, $request->user(), $comment);

        return $this->done($research, 'Research approved and published.');
    }

    public function requestChanges(Request $request, Research $research, ResearchWorkflow $workflow): JsonResponse
    {
        $this->allow($request, 'research.manage');
        $comment = $request->validate(['comment' => ['required', 'string', 'max:2000']])['comment'];
        $workflow->requestChanges($research, $request->user(), $comment);

        return $this->done($research, 'Changes requested. The author has been notified.');
    }

    public function reject(Request $request, Research $research, ResearchWorkflow $workflow): JsonResponse
    {
        $this->allow($request, 'research.manage');
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;
        $workflow->reject($research, $request->user(), $comment);

        return $this->done($research, 'Research rejected.');
    }

    public function unpublish(Request $request, Research $research, ResearchWorkflow $workflow): JsonResponse
    {
        $this->allow($request, 'research.manage');
        $workflow->unpublish($research, $request->user());

        return $this->done($research, 'Research unpublished.');
    }

    public function destroy(Request $request, Research $research, DeletionService $deletions): JsonResponse
    {
        $this->allow($request, 'research.manage');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];
        $deletions->delete($research, $reason);

        return response()->json(['message' => 'Research deleted.']);
    }

    // --- Categories -------------------------------------------------------

    public function categories(Request $request): JsonResponse
    {
        $this->allow($request, 'research.view');

        return response()->json([
            'data' => ResearchCategory::withCount('researches')->orderBy('position')->orderBy('name')->get()
                ->map(fn (ResearchCategory $c) => $this->categoryRow($c))->values(),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->allow($request, 'research.manage');
        $category = ResearchCategory::create($this->categoryData($request));
        ActivityLog::log('research_category_created', 'ResearchCategory', $category->id, ['name' => $category->name]);

        return response()->json(['data' => $this->categoryRow($category->loadCount('researches')), 'message' => 'Category added.'], 201);
    }

    public function updateCategory(Request $request, ResearchCategory $category): JsonResponse
    {
        $this->allow($request, 'research.manage');
        $category->update($this->categoryData($request, $category));

        return response()->json(['data' => $this->categoryRow($category->loadCount('researches')), 'message' => 'Category updated.']);
    }

    public function destroyCategory(Request $request, ResearchCategory $category): JsonResponse
    {
        $this->allow($request, 'research.manage');
        $category->researches()->update(['research_category_id' => null]);
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    // --- Helpers ----------------------------------------------------------

    private function row(Research $r): array
    {
        return [
            'id' => $r->id,
            'title' => $r->title,
            'slug' => $r->slug,
            'status' => $r->status,
            'status_label' => $r->statusLabel(),
            'category' => $r->category?->name,
            'author' => $r->author?->name,
            'reviewer' => $r->reviewer?->name,
            'chapters_count' => (int) ($r->chapters_count ?? $r->chapters()->count()),
            'updated_at' => $r->updated_at?->toIso8601String(),
            'published_at' => $r->published_at?->toIso8601String(),
        ];
    }

    private function categoryRow(ResearchCategory $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'icon' => $c->icon,
            'position' => (int) $c->position,
            'researches_count' => (int) ($c->researches_count ?? 0),
        ];
    }

    /** A blank slug keeps the current one (or is generated from the name). */
    private function categoryData(Request $request, ?ResearchCategory $category = null): array
    {
        return array_filter($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('research_categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:60'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]), fn ($value, $key) => $key !== 'slug' || filled($value), ARRAY_FILTER_USE_BOTH);
    }

    private function done(Research $research, string $message): JsonResponse
    {
        return response()->json(['data' => $this->row($research->refresh()->loadCount('chapters')), 'message' => $message]);
    }

    private function can(Request $request, string $permission): bool
    {
        $user = $request->user();

        return $user->isSuperAdmin() || $user->hasPermission($permission)
            || ($permission === 'research.view' && $user->hasPermission('research.manage'));
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($this->can($request, $permission), 403, 'You do not have access to research review.');
    }
}
