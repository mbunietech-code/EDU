<?php

namespace App\Http\Controllers\Admin\Learning;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\LearningVideo;
use App\Services\Learning\LearningAnalytics;
use App\Services\Learning\LearningDeletionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Trashed learning content: restore or delete forever (per-type permission check).
 */
class TrashController extends Controller
{
    private const PER_PAGE = 20;

    /** type => [label, permission, model] */
    public const TYPES = [
        'category' => ['Categories', 'learning.manage', LearningCategory::class],
        'course' => ['Courses', 'learning.manage', LearningCourse::class],
        'video' => ['Lessons', 'learning.manage', LearningVideo::class],
        'room' => ['Live rooms', 'rooms.manage', LearningRoom::class],
    ];

    public function __construct(protected LearningDeletionService $deletions)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('learning.trash');

        $allowed = $this->allowedTypes();
        abort_if($allowed === [], 403);

        $type = in_array($request->query('type'), $allowed, true) ? (string) $request->query('type') : $allowed[0];

        $counts = [];
        foreach ($allowed as $t) {
            $counts[$t] = self::TYPES[$t][2]::onlyTrashed()->count();
        }

        $query = self::TYPES[$type][2]::onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');
        $query = match ($type) {
            'course' => $query->with(['category' => fn ($q) => $q->withTrashed()->select(['id', 'name', 'deleted_at'])])->withCount('videos'),
            'video' => $query->with([
                'category' => fn ($q) => $q->withTrashed()->select(['id', 'name', 'deleted_at']),
                'course' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'deleted_at']),
            ]),
            'room' => $query->with('host:id,name')->withCount('sessions'),
            default => $query->withCount('courses'),
        };

        return view('admin.learning.trash.index', [
            'type' => $type,
            'types' => array_intersect_key(self::TYPES, array_flip($allowed)),
            'counts' => $counts,
            'items' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'retentionDays' => max(1, (int) config('learning.trash_retention_days', 30)),
        ]);
    }

    public function restore(Request $request, string $type, int $id, LearningAnalytics $analytics): RedirectResponse
    {
        Gate::authorize('learning.trash');
        $this->authorizeType($type);

        try {
            $model = $this->deletions->restore($type, $id);
        } catch (LearningDeletionBlocked $e) {
            return back()->with('error', $e->getMessage());
        } catch (ModelNotFoundException|\InvalidArgumentException) {
            return back()->with('error', 'That item is no longer in the trash.');
        }

        $analytics->forget();

        return back()->with('success', '“'.$this->label($model).'” restored.');
    }

    public function destroy(Request $request, string $type, int $id, LearningAnalytics $analytics): RedirectResponse
    {
        Gate::authorize('learning.trash');
        $this->authorizeType($type);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        $model = self::TYPES[$type][2]::onlyTrashed()->find($id);
        if (! $model) {
            return back()->with('error', 'That item is no longer in the trash.');
        }
        $label = $this->label($model);

        try {
            $this->deletions->purge($type, $id);
        } catch (LearningDeletionBlocked $e) {
            return back()->with('error', $e->getMessage());
        } catch (ModelNotFoundException|\InvalidArgumentException) {
            return back()->with('error', 'That item is no longer in the trash.');
        }

        ActivityLog::log('learning_trash_purged', class_basename($model), $id, [
            'type' => $type,
            'label' => $label,
            'reason' => $data['reason'],
        ]);

        $analytics->forget();

        return back()->with('success', '“'.$label.'” was deleted permanently.');
    }

    /** @return list<string> */
    private function allowedTypes(): array
    {
        return array_values(array_filter(
            array_keys(self::TYPES),
            fn (string $t) => Gate::allows(self::TYPES[$t][1]),
        ));
    }

    private function authorizeType(string $type): void
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        abort_unless(Gate::allows(self::TYPES[$type][1]), 403);
    }

    private function label($model): string
    {
        return (string) ($model->title ?? $model->name ?? ('#'.$model->getKey()));
    }
}
