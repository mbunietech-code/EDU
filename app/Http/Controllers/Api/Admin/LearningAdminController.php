<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Http\Controllers\Admin\Learning\TrashController;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\User;
use App\Services\Learning\GuestAccessService;
use App\Services\Learning\LearningAnalytics;
use App\Services\Learning\LearningDeletionService;
use App\Services\Learning\LearningNotifier;
use App\Services\Learning\LearningStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Learning management for admins in the app, mirroring the web
 * Admin\Learning controllers: categories, courses, enrolments, the trash
 * and the platform guest-link switch. learning.view to read,
 * learning.manage to change, learning.trash for the trash.
 */
class LearningAdminController extends Controller
{
    public function __construct(protected LearningAnalytics $analytics)
    {
    }

    /** Counts, the guest-link switch and the options the forms need. */
    public function overview(Request $request, GuestAccessService $guests): JsonResponse
    {
        $this->allow($request, 'learning.view');

        return response()->json(['data' => [
            'counts' => [
                'categories' => LearningCategory::query()->count(),
                'courses' => LearningCourse::query()->count(),
                'published_courses' => LearningCourse::query()->where('status', 'published')->count(),
                'enrolments' => LearningEnrollment::query()->count(),
                'live_rooms' => LearningRoom::query()->where('status', 'live')->count(),
            ],
            'can_manage' => $this->can($request, 'learning.manage'),
            'can_trash' => $this->can($request, 'learning.trash'),
            'can_manage_rooms' => $this->can($request, 'rooms.manage'),
            'guest_links' => $guests->enabled(),
            'levels' => LearningCourse::LEVELS,
            'access' => LearningCourse::ACCESS,
            'instructors' => $this->eligibleInstructors()->where('status', 'active')->orderBy('name')->limit(200)->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
        ]]);
    }

    public function guestLinks(Request $request, GuestAccessService $guests): JsonResponse
    {
        $this->allow($request, 'rooms.manage');
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $guests->setEnabled((bool) $data['enabled'], $request->user());

        return response()->json([
            'guest_links' => (bool) $data['enabled'],
            'message' => $data['enabled']
                ? 'Guest links are on: hosts can share a link that works without an account.'
                : 'Guest links are off: every guest link stops working and guests are signed out.',
        ]);
    }

    // --- Categories -------------------------------------------------------

    public function categories(Request $request): JsonResponse
    {
        $this->allow($request, 'learning.view');

        return response()->json(['data' => LearningCategory::query()
            ->withCount(['courses', 'videos', 'rooms'])
            ->orderBy('position')->orderBy('name')->get()
            ->map(fn (LearningCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'icon' => $c->icon,
                'position' => (int) $c->position,
                'courses_count' => (int) $c->courses_count,
                'videos_count' => (int) $c->videos_count,
                'rooms_count' => (int) $c->rooms_count,
            ])->values()]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->allow($request, 'learning.manage');
        $data = $this->categoryData($request);
        $data['position'] ??= (int) LearningCategory::query()->max('position') + 1;
        $data['created_by'] = $request->user()->id;

        $category = LearningCategory::create($data);
        ActivityLog::log('learning_category_created', 'LearningCategory', $category->id, ['name' => $category->name]);

        return response()->json(['data' => ['id' => $category->id], 'message' => 'Category “'.$category->name.'” created.'], 201);
    }

    public function updateCategory(Request $request, LearningCategory $category): JsonResponse
    {
        $this->allow($request, 'learning.manage');
        $data = $this->categoryData($request);
        $data['position'] ??= (int) $category->position;
        $category->update($data);
        ActivityLog::log('learning_category_updated', 'LearningCategory', $category->id, ['name' => $category->name]);

        return response()->json(['message' => 'Category “'.$category->name.'” updated.']);
    }

    public function destroyCategory(Request $request, LearningCategory $category, LearningDeletionService $deletions): JsonResponse
    {
        $this->allow($request, 'learning.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->blocked(fn () => $deletions->deleteCategory($category, $data['reason']));

        return response()->json(['message' => 'Category “'.$category->name.'” moved to the trash.']);
    }

    // --- Courses ----------------------------------------------------------

    public function courses(Request $request): JsonResponse
    {
        $this->allow($request, 'learning.view');
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(LearningCourse::STATUSES)],
            'category' => ['nullable', 'integer'],
        ]);

        $page = LearningCourse::query()
            ->with(['category:id,name', 'instructor:id,name'])
            ->withCount(['videos', 'enrollments'])
            ->when($data['category'] ?? null, fn (Builder $q, $id) => $q->where('learning_category_id', $id))
            ->when($data['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when(trim((string) ($data['q'] ?? '')) !== '', function (Builder $q) use ($data) {
                $like = '%'.addcslashes(trim($data['q']), '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhere('summary', 'like', $like));
            })
            ->orderBy('position')->orderBy('title')
            ->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (LearningCourse $c) => $this->courseRow($c))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function course(Request $request, LearningCourse $course): JsonResponse
    {
        $this->allow($request, 'learning.view');
        $course->load(['category:id,name', 'instructor:id,name'])->loadCount(['videos', 'enrollments', 'topics']);
        $showEmail = (bool) $request->user()->is_admin;

        return response()->json(['data' => $this->courseRow($course) + [
            'learning_category_id' => $course->learning_category_id,
            'instructor_id' => $course->instructor_id,
            'summary' => $course->summary,
            'description' => $course->description,
            'level' => $course->level,
            'access' => $course->access,
            'position' => (int) $course->position,
            'topics_count' => (int) $course->topics_count,
            'enrolments' => $course->enrollments()->with('user:id,name,email')
                ->orderByDesc('enrolled_at')->orderByDesc('id')->limit(200)->get()
                ->map(fn (LearningEnrollment $e) => [
                    'id' => $e->id,
                    'user_id' => $e->user_id,
                    'name' => $e->user?->name ?? 'Former member',
                    'email' => $showEmail ? $e->user?->email : null,
                    'source' => $e->source,
                    'enrolled_at' => $e->enrolled_at?->toIso8601String(),
                ])->values(),
        ]]);
    }

    public function storeCourse(Request $request, LearningNotifier $notifier, LearningStorage $storage): JsonResponse
    {
        $this->allow($request, 'learning.manage');
        $data = $this->courseData($request);
        $actor = $request->user();
        $thumbnail = $request->file('thumbnail') ? $storage->storeThumbnail($request->file('thumbnail')) : null;

        try {
            $course = DB::transaction(function () use ($data, $actor, $thumbnail) {
                $course = LearningCourse::create(array_merge($data, [
                    'position' => $data['position'] ?? ((int) LearningCourse::query()->where('learning_category_id', $data['learning_category_id'])->max('position') + 1),
                    'thumbnail_path' => $thumbnail,
                    'published_at' => $data['status'] === 'published' ? now() : null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]));
                ActivityLog::log('learning_course_created', 'LearningCourse', $course->id, ['title' => $course->title, 'status' => $course->status]);

                return $course;
            });
        } catch (\Throwable $e) {
            $storage->deleteFile($storage->publicDisk(), $thumbnail);
            throw $e;
        }

        $this->analytics->forget();
        if ($course->isPublished() && $request->boolean('notify')) {
            $notifier->notifyNewCourse($course, $actor);
        }

        return response()->json(['data' => ['id' => $course->id], 'message' => 'Course “'.$course->title.'” created.'], 201);
    }

    /** POST (may carry a new thumbnail file). */
    public function updateCourse(Request $request, LearningCourse $course, LearningNotifier $notifier, LearningStorage $storage): JsonResponse
    {
        $this->allow($request, 'learning.manage');
        $data = $this->courseData($request, $course);
        $actor = $request->user();
        $firstPublish = $data['status'] === 'published' && $course->published_at === null;
        $oldThumbnail = $course->thumbnail_path;
        $newThumbnail = $request->file('thumbnail') ? $storage->storeThumbnail($request->file('thumbnail')) : null;

        if ($newThumbnail !== null) {
            $data['thumbnail_path'] = $newThumbnail;
        } elseif ($request->boolean('remove_thumbnail')) {
            $data['thumbnail_path'] = null;
        }
        if ($firstPublish) {
            $data['published_at'] = now();
        }
        $data['position'] ??= (int) $course->position;
        $data['updated_by'] = $actor->id;

        try {
            $course->update($data);
            ActivityLog::log('learning_course_updated', 'LearningCourse', $course->id, ['title' => $course->title]);
        } catch (\Throwable $e) {
            $storage->deleteFile($storage->publicDisk(), $newThumbnail);
            throw $e;
        }
        if ($oldThumbnail && $oldThumbnail !== $course->thumbnail_path) {
            $storage->deleteFile($storage->publicDisk(), $oldThumbnail);
        }

        $this->analytics->forget();
        if ($firstPublish && $request->boolean('notify')) {
            $notifier->notifyNewCourse($course, $actor);
        }

        return response()->json(['data' => $this->courseRow($course->refresh()), 'message' => 'Course “'.$course->title.'” saved.']);
    }

    public function destroyCourse(Request $request, LearningCourse $course, LearningDeletionService $deletions): JsonResponse
    {
        $this->allow($request, 'learning.manage');
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'with_videos' => ['nullable', 'boolean'],
        ]);
        $this->blocked(fn () => $deletions->deleteCourse($course, $data['reason'], (bool) ($data['with_videos'] ?? false)));
        $this->analytics->forget();

        return response()->json(['message' => 'Course “'.$course->title.'” moved to the trash.']);
    }

    // --- Enrolments -------------------------------------------------------

    public function enrol(Request $request, LearningCourse $course): JsonResponse
    {
        $this->allow($request, 'learning.manage');
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:200'],
            'user_ids.*' => ['integer', 'distinct'],
        ]);

        $users = User::query()->realUsers()->whereIn('id', array_map('intval', $data['user_ids']))->where('status', 'active')->get(['id']);
        if ($users->isEmpty()) {
            throw ValidationException::withMessages(['user_ids' => 'None of the chosen members can be enrolled (they must be active members).']);
        }

        $created = [];
        DB::transaction(function () use ($users, $course, $request, &$created) {
            foreach ($users as $user) {
                $enrollment = LearningEnrollment::firstOrCreate(
                    ['user_id' => $user->id, 'learning_course_id' => $course->id],
                    ['source' => 'admin', 'enrolled_by' => $request->user()->id, 'enrolled_at' => now()],
                );
                if ($enrollment->wasRecentlyCreated) {
                    $created[] = $user->id;
                }
            }
            ActivityLog::log('learning_enrollments_added', 'LearningCourse', $course->id, ['title' => $course->title, 'user_ids' => $created]);
        });
        $this->analytics->forget();

        $already = $users->count() - count($created);

        return response()->json([
            'added' => count($created),
            'message' => count($created).' '.Str::plural('learner', count($created)).' enrolled.'.($already > 0 ? ' '.$already.' already enrolled.' : ''),
        ]);
    }

    public function unenrol(Request $request, LearningCourse $course, LearningEnrollment $enrollment): JsonResponse
    {
        $this->allow($request, 'learning.manage');
        abort_unless((int) $enrollment->learning_course_id === (int) $course->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        $enrollment->delete();
        ActivityLog::log('learning_enrollment_removed', 'LearningEnrollment', $enrollment->id, [
            'course_id' => $course->id,
            'user_id' => $enrollment->user_id,
            'reason' => $data['reason'],
        ]);
        $this->analytics->forget();

        return response()->json(['message' => 'Removed from the course. Their lesson progress is kept.']);
    }

    // --- Trash ------------------------------------------------------------

    public function trash(Request $request): JsonResponse
    {
        $this->allow($request, 'learning.trash');
        $types = $this->trashTypes($request);
        abort_if($types === [], 403);
        $type = in_array($request->query('type'), $types, true) ? (string) $request->query('type') : $types[0];
        $model = TrashController::TYPES[$type][2];

        $items = $model::onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id')->limit(100)->get();

        return response()->json([
            'types' => collect($types)->mapWithKeys(fn ($t) => [$t => [
                'label' => TrashController::TYPES[$t][0],
                'count' => TrashController::TYPES[$t][2]::onlyTrashed()->count(),
            ]]),
            'type' => $type,
            'retention_days' => max(1, (int) config('learning.trash_retention_days', 30)),
            'data' => $items->map(fn ($m) => [
                'id' => $m->getKey(),
                'label' => (string) ($m->title ?? $m->name ?? ('#'.$m->getKey())),
                'deleted_at' => $m->deleted_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function restore(Request $request, string $type, int $id, LearningDeletionService $deletions): JsonResponse
    {
        $this->allowTrashType($request, $type);

        try {
            $model = $deletions->restore($type, $id);
        } catch (LearningDeletionBlocked $e) {
            throw ValidationException::withMessages(['trash' => $e->getMessage()]);
        } catch (ModelNotFoundException|\InvalidArgumentException) {
            abort(404, 'That item is no longer in the trash.');
        }
        $this->analytics->forget();

        return response()->json(['message' => '“'.($model->title ?? $model->name ?? '#'.$id).'” restored.']);
    }

    public function purge(Request $request, string $type, int $id, LearningDeletionService $deletions): JsonResponse
    {
        $this->allowTrashType($request, $type);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $model = TrashController::TYPES[$type][2]::onlyTrashed()->find($id);
        abort_unless($model, 404, 'That item is no longer in the trash.');
        $label = (string) ($model->title ?? $model->name ?? '#'.$id);

        try {
            $deletions->purge($type, $id);
        } catch (LearningDeletionBlocked $e) {
            throw ValidationException::withMessages(['trash' => $e->getMessage()]);
        }
        ActivityLog::log('learning_trash_purged', class_basename($model), $id, ['type' => $type, 'label' => $label, 'reason' => $data['reason']]);
        $this->analytics->forget();

        return response()->json(['message' => '“'.$label.'” was deleted permanently.']);
    }

    // --- Helpers ----------------------------------------------------------

    private function courseRow(LearningCourse $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'slug' => $c->slug,
            'status' => $c->status,
            'category' => $c->category?->name,
            'instructor' => $c->instructor?->name,
            'videos_count' => (int) ($c->videos_count ?? 0),
            'enrolments_count' => (int) ($c->enrollments_count ?? 0),
            'thumbnail_url' => $c->thumbnailUrl(),
        ];
    }

    private function categoryData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9 _.:-]+$/'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        return [
            'name' => trim($data['name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'icon' => filled($data['icon'] ?? null) ? trim($data['icon']) : null,
            'position' => isset($data['position']) ? (int) $data['position'] : null,
        ];
    }

    private function courseData(Request $request, ?LearningCourse $course = null): array
    {
        $data = $request->validate([
            'learning_category_id' => ['required', 'integer', Rule::exists('learning_categories', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:20000'],
            'level' => ['nullable', Rule::in(array_keys(LearningCourse::LEVELS))],
            'instructor_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) use ($course) {
                if ($course && (int) $course->instructor_id === (int) $value) {
                    return;
                }
                if (! $this->eligibleInstructors()->whereKey((int) $value)->exists()) {
                    $fail('Choose an instructor (a member with instructor access, or an admin).');
                }
            }],
            'access' => ['required', Rule::in(array_keys(LearningCourse::ACCESS))],
            'status' => ['required', Rule::in(LearningCourse::STATUSES)],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'thumbnail' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'remove_thumbnail' => ['nullable', 'boolean'],
            'notify' => ['nullable', 'boolean'],
        ]);

        return [
            'learning_category_id' => (int) $data['learning_category_id'],
            'title' => trim($data['title']),
            'summary' => filled($data['summary'] ?? null) ? trim($data['summary']) : null,
            'description' => filled($data['description'] ?? null) ? $data['description'] : null,
            'level' => $data['level'] ?? null,
            'instructor_id' => isset($data['instructor_id']) ? (int) $data['instructor_id'] : null,
            'access' => $data['access'],
            'status' => $data['status'],
            'position' => isset($data['position']) ? (int) $data['position'] : null,
        ];
    }

    private function eligibleInstructors(): Builder
    {
        return User::query()->realUsers()->where(fn (Builder $q) => $q->where('can_teach', true)->orWhere('is_admin', true));
    }

    /** @return list<string> */
    private function trashTypes(Request $request): array
    {
        return array_values(array_filter(
            array_keys(TrashController::TYPES),
            fn (string $t) => $this->can($request, TrashController::TYPES[$t][1]),
        ));
    }

    private function allowTrashType(Request $request, string $type): void
    {
        $this->allow($request, 'learning.trash');
        abort_unless(isset(TrashController::TYPES[$type]), 404);
        $this->allow($request, TrashController::TYPES[$type][1]);
    }

    private function blocked(callable $action): void
    {
        try {
            $action();
        } catch (LearningDeletionBlocked $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }
    }

    private function can(Request $request, string $ability): bool
    {
        return Gate::forUser($request->user())->allows($ability);
    }

    private function allow(Request $request, string $ability): void
    {
        abort_unless($this->can($request, $ability), 403, 'You do not have access to this part of Learning.');
    }
}
