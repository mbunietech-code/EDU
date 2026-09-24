<?php

namespace App\Http\Controllers\Admin\Learning;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningCategory;
use App\Services\Learning\LearningDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Learning categories (admin).
 */
class CategoryController extends Controller
{
    public function index(Request $request, LearningDeletionService $deletions): View
    {
        Gate::authorize('learning.view');

        $categories = LearningCategory::query()
            ->withCount(['courses', 'videos', 'rooms'])
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $canManage = Gate::allows('learning.manage');
        $impact = [];
        if ($canManage) {
            foreach ($categories as $category) {
                $impact[$category->id] = $deletions->impact($category);
            }
        }

        return view('admin.learning.categories.index', compact('categories', 'canManage', 'impact'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $this->validated($request);
        $data['position'] ??= (int) LearningCategory::query()->max('position') + 1;
        $data['created_by'] = $request->user()->id;

        $category = LearningCategory::create($data);

        ActivityLog::log('learning_category_created', 'LearningCategory', $category->id, ['name' => $category->name]);

        return back()->with('success', 'Category “'.$category->name.'” created.');
    }

    public function update(Request $request, LearningCategory $category): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $this->validated($request);
        $data['position'] ??= (int) $category->position;

        $category->update($data);

        ActivityLog::log('learning_category_updated', 'LearningCategory', $category->id, [
            'name' => $category->name,
            'changes' => array_keys($category->getChanges()),
        ]);

        return back()->with('success', 'Category “'.$category->name.'” updated.');
    }

    public function destroy(Request $request, LearningCategory $category, LearningDeletionService $deletions): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        try {
            $deletions->deleteCategory($category, $data['reason']);
        } catch (LearningDeletionBlocked $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Category “'.$category->name.'” moved to the trash.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9 _.:-]+$/'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $data['name'] = trim($data['name']);
        $data['description'] = filled($data['description'] ?? null) ? trim($data['description']) : null;
        $data['icon'] = filled($data['icon'] ?? null) ? trim($data['icon']) : null;
        $data['position'] = isset($data['position']) ? (int) $data['position'] : null;

        return $data;
    }
}
