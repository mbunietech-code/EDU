<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningVideo;
use App\Services\Learning\ProgressService;
use Illuminate\Http\Request;

/**
 * Learning categories for members (bound by slug).
 *
 * Every count and list only includes published content the viewer may open
 * (LearningCourse / LearningVideo ::visibleTo), so nothing enrolled-only,
 * draft or private ever shows up for someone who cannot watch it.
 */
class CategoryController extends Controller
{
    /** Courses shown on a category page before "View all courses". */
    private const COURSE_LIMIT = 8;

    public function index(Request $request)
    {
        $user = $request->user();

        $categories = LearningCategory::query()
            ->withCount([
                'courses as courses_count' => fn ($q) => $q->published()->visibleTo($user),
                'videos as videos_count' => fn ($q) => $q->published()->visibleTo($user),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('learn.categories.index', compact('categories'));
    }

    public function show(Request $request, LearningCategory $category, ProgressService $progress)
    {
        $user = $request->user();

        // Whitelisted, not validated: a stale/odd query string falls back to the default.
        $sort = in_array($request->query('sort'), ['latest', 'popular'], true) ? $request->query('sort') : 'latest';

        $coursesQuery = LearningCourse::query()
            ->where('learning_category_id', $category->id)
            ->published()
            ->visibleTo($user);

        $courseTotal = (clone $coursesQuery)->count();

        $courses = $coursesQuery
            ->with(['category:id,name,slug', 'instructor:id,name'])
            ->withCount(['videos' => fn ($q) => $q->published()->visibleTo($user)])
            ->orderBy('position')
            ->orderBy('title')
            ->limit(self::COURSE_LIMIT)
            ->get();

        // Bounded (≤ 8 courses): the service is the single source of truth for roll-ups.
        $courseProgress = $courses->mapWithKeys(fn (LearningCourse $c) => [$c->id => $progress->courseProgress($user, $c)]);

        $videos = LearningVideo::query()
            ->where('learning_category_id', $category->id)
            ->published()
            ->visibleTo($user)
            ->with([
                'category:id,name,slug',
                'instructor:id,name',
                'progress' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->when($sort === 'popular',
                fn ($q) => $q->orderByDesc('views')->orderByDesc('published_at'),
                fn ($q) => $q->orderByDesc('published_at'))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('learn.categories.show', [
            'category' => $category,
            'courses' => $courses,
            'courseTotal' => $courseTotal,
            'courseProgress' => $courseProgress,
            'videos' => $videos,
            'sort' => $sort,
        ]);
    }
}
