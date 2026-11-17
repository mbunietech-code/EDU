<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResearchRequestStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The research work process: add, rename, recolour, reorder, switch off and
 * delete stages. System stages (Received, Quoted, Revisions, Delivered,
 * Closed…) can be renamed and reordered but not deleted, since the workflow
 * moves requests into them by itself. A stage still holding requests cannot
 * be deleted either.
 */
class ResearchRequestStageController extends Controller
{
    public function index(Request $request): View
    {
        $stages = ResearchRequestStage::query()->ordered()
            ->withCount(['requests as active_count' => fn ($q) => $q->where('status', 'active')])
            ->withCount('requests')
            ->get();

        return view('admin.research-requests.stages', [
            'stages' => $stages,
            'canManage' => $request->user()->can('research_requests.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort_order'] = (int) ResearchRequestStage::query()->max('sort_order') + 1;
        if ($request->filled('after')) {
            // Put it right after a chosen stage.
            $after = ResearchRequestStage::query()->find($request->integer('after'));
            if ($after) {
                ResearchRequestStage::query()->where('sort_order', '>', $after->sort_order)->increment('sort_order');
                $data['sort_order'] = $after->sort_order + 1;
            }
        }

        $stage = ResearchRequestStage::create($data);

        return back()->with('success', 'Stage "'.$stage->name.'" added.');
    }

    public function update(Request $request, ResearchRequestStage $stage): RedirectResponse
    {
        $data = $this->validated($request);
        if ($stage->isSystem()) {
            // The workflow relies on these being reachable.
            $data['is_active'] = true;
        }
        $stage->update($data);

        return back()->with('success', 'Stage "'.$stage->name.'" saved.');
    }

    /** New order from the drag-and-drop list (ids top to bottom). */
    public function reorder(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', Rule::exists('research_request_stages', 'id')],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values(array_unique($data['order'])) as $i => $id) {
                ResearchRequestStage::query()->whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return $request->wantsJson()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Stage order saved.');
    }

    public function move(Request $request, ResearchRequestStage $stage): RedirectResponse
    {
        $dir = $request->input('dir') === 'up' ? 'up' : 'down';
        $all = ResearchRequestStage::query()->ordered()->get()->values();
        $i = $all->search(fn ($s) => $s->id === $stage->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;

        if ($i !== false && $j >= 0 && $j < $all->count()) {
            $ids = $all->pluck('id')->all();
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            DB::transaction(function () use ($ids) {
                foreach ($ids as $n => $id) {
                    ResearchRequestStage::query()->whereKey($id)->update(['sort_order' => $n + 1]);
                }
            });
        }

        return back();
    }

    public function destroy(ResearchRequestStage $stage): RedirectResponse
    {
        if ($stage->isSystem()) {
            return back()->with('error', '"'.$stage->name.'" is a built-in stage — rename or reorder it instead.');
        }
        if ($stage->requests()->exists()) {
            return back()->with('error', 'Move the requests out of "'.$stage->name.'" first, or switch the stage off.');
        }

        $stage->delete();

        return back()->with('success', 'Stage deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', Rule::in(ResearchRequestStage::COLORS)],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'allows_cancel' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'color' => $data['color'],
            'progress' => (int) $data['progress'],
            'allows_cancel' => $request->boolean('allows_cancel'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
