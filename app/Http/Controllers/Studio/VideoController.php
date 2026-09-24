<?php

namespace App\Http\Controllers\Studio;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Http\Controllers\Controller;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\User;
use App\Services\Learning\ChunkedUploadService;
use App\Services\Learning\LearningDeletionService;
use App\Services\Learning\VideoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Video lesson management in the Teaching Studio (bound by id).
 *
 * Who: learning.view staff see every lesson (read-only unless they also
 * hold learning.manage); instructors see and edit only lessons they are the
 * instructor of. Every write is authorised through LearningVideoPolicy and
 * performed by VideoService / LearningDeletionService.
 *
 * Product rule (enforced here, server-side): an instructor without
 * learning.manage may only place a lesson in a course they instruct
 * (learning_courses.instructor_id = them) or leave it standalone in any
 * category. Content managers may use any course.
 */
class VideoController extends Controller
{
    private const PER_PAGE = 20;

    private const THUMBNAIL_MAX_KB = 5120;

    private const TABS = ['all' => 'All', 'published' => 'Published', 'drafts' => 'Drafts'];

    public function __construct(protected VideoService $videos)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->canUseLessons($user), 403);

        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'all';
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $categoryId = $request->integer('category') ?: null;
        $courseId = $request->integer('course') ?: null;
        $seesAll = $user->hasPermission('learning.view') || $user->hasPermission('learning.manage');

        $base = LearningVideo::query()
            ->when(! $seesAll, fn (Builder $q) => $q->where('instructor_id', $user->id))
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', '%'.addcslashes($search, '\\%_').'%'))
            ->when($categoryId, fn (Builder $q) => $q->where('learning_category_id', $categoryId))
            ->when($courseId, fn (Builder $q) => $q->where('learning_course_id', $courseId));

        $counts = [
            'all' => (clone $base)->count(),
            'published' => (clone $base)->where('status', 'published')->count(),
            'drafts' => (clone $base)->where('status', 'draft')->count(),
        ];

        $videos = (clone $base)
            ->when($tab === 'published', fn (Builder $q) => $q->where('status', 'published'))
            ->when($tab === 'drafts', fn (Builder $q) => $q->where('status', 'draft'))
            ->with([
                'category:id,name',
                'course' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'deleted_at']),
                'instructor:id,name',
            ])
            ->withCount([
                'progress',
                'progress as viewers_count' => fn (Builder $q) => $q->where('play_count', '>', 0),
                'progress as completions_count' => fn (Builder $q) => $q->whereNotNull('completed_at'),
                'comments',
                'renditions',
                'resources',
            ])
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $categories = LearningCategory::query()->orderBy('position')->orderBy('name')->get(['id', 'name']);
        $courses = LearningCourse::query()
            ->when(! $seesAll, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('instructor_id', $user->id)
                ->orWhereIn('id', LearningVideo::query()->select('learning_course_id')
                    ->where('instructor_id', $user->id)
                    ->whereNotNull('learning_course_id'))))
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('studio.videos.index', [
            'videos' => $videos,
            'impacts' => $videos->getCollection()->mapWithKeys(fn (LearningVideo $v) => [$v->id => $this->impactFromCounts($v)])->all(),
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => $counts,
            'search' => $search,
            'categoryId' => $categoryId,
            'courseId' => $courseId,
            'categories' => $categories,
            'courses' => $courses,
            'seesAll' => $seesAll,
            'filtered' => $search !== '' || $categoryId || $courseId,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LearningVideo::class);

        $video = new LearningVideo(['visibility' => 'course', 'status' => 'draft']);

        return view('studio.videos.create', array_merge($this->formOptions($request->user(), null), [
            'video' => $video,
            'uploader' => $this->uploaderConfig('video', autoThumbnail: true, captureMeta: true, existing: $this->pendingUpload($request)),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LearningVideo::class);
        $user = $request->user();

        $data = $request->validate($this->rules(creating: true), $this->messages());
        $this->assertCourseAllowed($user, $data['learning_course_id'] ?? null, null);

        $publish = ($data['action'] ?? 'draft') === 'publish';

        $payload = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'learning_category_id' => $data['learning_category_id'] ?? null,
            'learning_course_id' => $data['learning_course_id'] ?? null,
            'learning_topic_id' => $data['learning_topic_id'] ?? null,
            'tags' => $data['tags'] ?? null,
            'visibility' => $data['visibility'],
            'status' => $publish ? 'published' : 'draft',
            'upload_token' => $data['upload_token'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'thumbnail' => $this->thumbnailFrom($request),
            'notify' => $request->boolean('notify'),
        ] + $this->instructorPayload($request, $user, $data);

        $video = $this->videos->create($payload, $user);

        return redirect()
            ->route('studio.videos.edit', $video)
            ->with('success', $video->isPublished()
                ? 'Lesson published'.($request->boolean('notify') ? ' — eligible learners are being notified.' : '.')
                : 'Lesson saved as a draft.');
    }

    public function edit(Request $request, LearningVideo $video, LearningDeletionService $deletions): View
    {
        $this->authorize('update', $video);

        $video->load([
            'category:id,name',
            'course' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'learning_category_id', 'deleted_at']),
            'topic:id,title',
            'instructor:id,name',
            'renditions',
            'resources',
        ]);

        return view('studio.videos.edit', array_merge($this->formOptions($request->user(), $video), [
            'video' => $video,
            'impact' => $deletions->impact($video),
            'replaceUploader' => $this->uploaderConfig('video', autoThumbnail: $video->thumbnail_path === null, captureMeta: true, required: true),
            'renditionUploader' => $this->uploaderConfig('rendition', autoThumbnail: false, captureMeta: false, required: true),
            'qualities' => array_keys(LearningVideo::QUALITIES),
            'resourceExtensions' => array_values(config('learning.resource_extensions', [])),
            'maxResourceMb' => (int) config('learning.max_resource_mb', 50),
        ]));
    }

    public function update(Request $request, LearningVideo $video): RedirectResponse
    {
        $this->authorize('update', $video);
        $user = $request->user();

        $data = $request->validate($this->rules(creating: false), $this->messages());
        $this->assertCourseAllowed($user, $data['learning_course_id'] ?? null, $video);

        $payload = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'learning_category_id' => $data['learning_category_id'] ?? null,
            'learning_course_id' => $data['learning_course_id'] ?? null,
            'learning_topic_id' => $data['learning_topic_id'] ?? null,
            'tags' => $data['tags'] ?? null,
            'visibility' => $data['visibility'],
        ] + $this->instructorPayload($request, $user, $data);

        if ($thumbnail = $this->thumbnailFrom($request)) {
            $payload['thumbnail'] = $thumbnail;
        }

        $this->videos->update($video, $payload, $user);

        return redirect()->route('studio.videos.edit', $video)->with('success', 'Lesson details saved.');
    }

    public function destroy(Request $request, LearningVideo $video, LearningDeletionService $deletions): RedirectResponse
    {
        $this->authorize('delete', $video);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'Give a reason for deleting this lesson.',
            'reason.min' => 'The reason must be at least 3 characters.',
        ]);

        try {
            $deletions->deleteVideo($video, trim($data['reason']));
        } catch (LearningDeletionBlocked $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('studio.videos.index')
            ->with('success', '“'.$video->title.'” was moved to the trash. An administrator can restore it within '
                .(int) config('learning.trash_retention_days', 30).' days.');
    }

    public function publish(Request $request, LearningVideo $video): RedirectResponse
    {
        $this->authorize('publish', $video);

        $wasPublishedBefore = $video->published_at !== null;
        $notify = $request->boolean('notify', true);

        $this->videos->publish($video, $request->user(), $notify);

        return $this->backToVideo($video)->with('success', $notify && ! $wasPublishedBefore
            ? 'Lesson published — eligible learners are being notified.'
            : 'Lesson published.');
    }

    public function unpublish(Request $request, LearningVideo $video): RedirectResponse
    {
        $this->authorize('publish', $video);

        $this->videos->unpublish($video, $request->user());

        return $this->backToVideo($video)->with('success', 'Lesson moved back to drafts. Learners can no longer open it.');
    }

    public function replace(Request $request, LearningVideo $video): RedirectResponse
    {
        $this->authorize('update', $video);
        $user = $request->user();

        $data = $request->validate([
            'upload_token' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{40}\z/'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'width' => ['nullable', 'integer', 'min:0', 'max:16384'],
            'height' => ['nullable', 'integer', 'min:0', 'max:16384'],
            'auto_thumbnail' => ['nullable', 'file'],
        ], [
            'upload_token.required' => 'Upload the new video file first.',
            'upload_token.regex' => 'The upload is not valid. Please upload the file again.',
        ]);

        $this->videos->replaceFile(
            $video,
            $data['upload_token'],
            $user,
            isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            isset($data['width']) ? (int) $data['width'] : null,
            isset($data['height']) ? (int) $data['height'] : null,
        );

        // A lesson without a thumbnail picks up the frame captured from the new file.
        if ($video->thumbnail_path === null && ($auto = $this->autoThumbnail($request))) {
            try {
                $this->videos->update($video, ['thumbnail' => $auto], $user);
            } catch (ValidationException $e) {
                report($e); // the file itself was replaced; a bad captured frame is not worth failing for
            }
        }

        return redirect()->to(route('studio.videos.edit', $video).'#replace')->with('success', 'Video file replaced.');
    }

    public function destroyThumbnail(Request $request, LearningVideo $video): RedirectResponse
    {
        $this->authorize('update', $video);

        $this->videos->removeThumbnail($video, $request->user());

        return redirect()->route('studio.videos.edit', $video)->with('success', 'Thumbnail removed.');
    }

    // --- Internals -------------------------------------------------------
    private function canUseLessons(User $user): bool
    {
        return $user->canUploadLessons() || $user->hasPermission('learning.view');
    }

    /**
     * The same lines LearningDeletionService::impact() produces for a lesson,
     * built from the list's withCount() columns so the index does not run
     * four extra queries per row.
     *
     * @return list<string>
     */
    private function impactFromCounts(LearningVideo $video): array
    {
        $line = fn (int $n, string $one, ?string $many = null) => $n > 0
            ? $n.' '.($n === 1 ? $one : ($many ?? Str::plural($one)))
            : null;

        return array_values(array_filter([
            $line((int) $video->progress_count, 'learner progress record'),
            $line((int) $video->comments_count, 'comment'),
            $line((int) $video->renditions_count, 'extra quality', 'extra qualities'),
            $line((int) $video->resources_count, 'resource'),
            $video->hasFile() ? 'Video files are kept in the trash until it is purged' : null,
        ]));
    }

    /** @return array<string,mixed> */
    private function rules(bool $creating): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'learning_category_id' => ['nullable', 'required_without:learning_course_id', 'integer',
                Rule::exists('learning_categories', 'id')->whereNull('deleted_at')],
            'learning_course_id' => ['nullable', 'integer', Rule::exists('learning_courses', 'id')->whereNull('deleted_at')],
            'learning_topic_id' => ['nullable', 'integer', Rule::exists('learning_topics', 'id')],
            'tags' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(array_keys(LearningVideo::VISIBILITY))],
            'instructor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.self::THUMBNAIL_MAX_KB],
            'auto_thumbnail' => ['nullable', 'file'],
            'notify' => ['nullable', 'boolean'],
        ];

        if ($creating) {
            $rules += [
                'upload_token' => ['nullable', 'string', 'regex:/\A[A-Za-z0-9]{40}\z/'],
                'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
                'width' => ['nullable', 'integer', 'min:0', 'max:16384'],
                'height' => ['nullable', 'integer', 'min:0', 'max:16384'],
                'action' => ['nullable', Rule::in(['draft', 'publish'])],
            ];
        }

        return $rules;
    }

    /** @return array<string,string> */
    private function messages(): array
    {
        return [
            'learning_category_id.required_without' => 'Choose a category (or a course) for this lesson.',
            'learning_category_id.exists' => 'The selected category does not exist.',
            'learning_course_id.exists' => 'The selected course does not exist.',
            'learning_topic_id.exists' => 'The selected topic does not exist.',
            'upload_token.regex' => 'The upload is not valid. Please upload the file again.',
            'thumbnail.max' => 'The thumbnail may be at most '.(self::THUMBNAIL_MAX_KB / 1024).' MB.',
        ];
    }

    /**
     * Instructors (non-managers) may only use a course they instruct. When
     * editing, a lesson may stay in the course it is already in.
     */
    private function assertCourseAllowed(User $user, mixed $courseId, ?LearningVideo $video): void
    {
        if (blank($courseId) || $user->hasPermission('learning.manage')) {
            return;
        }

        $courseId = (int) $courseId;

        if ($video !== null && (int) $video->learning_course_id === $courseId) {
            return;
        }

        $teaches = LearningCourse::query()->whereKey($courseId)->where('instructor_id', $user->id)->exists();

        if (! $teaches) {
            throw ValidationException::withMessages([
                'learning_course_id' => 'You can only add lessons to courses you teach. Leave the course empty to publish a standalone lesson in any category.',
            ]);
        }
    }

    /** Only learning managers choose the instructor, and only among instructors or admins. */
    private function instructorPayload(Request $request, User $user, array $data): array
    {
        if (! $user->hasPermission('learning.manage') || ! $request->has('instructor_id')) {
            return [];
        }

        $id = $data['instructor_id'] ?? null;

        if ($id !== null) {
            $eligible = User::query()->whereKey($id)
                ->where(fn (Builder $q) => $q->where('can_teach', true)->orWhere('is_admin', true))
                ->exists();

            if (! $eligible) {
                throw ValidationException::withMessages([
                    'instructor_id' => 'The instructor must have instructor access (or be an administrator).',
                ]);
            }
        }

        return ['instructor_id' => $id];
    }

    /** The chosen thumbnail, else the frame the uploader captured from the video. */
    private function thumbnailFrom(Request $request): ?UploadedFile
    {
        $file = $request->file('thumbnail');

        return $file instanceof UploadedFile ? $file : $this->autoThumbnail($request);
    }

    /** The uploader's captured frame, only when it is a sane image (never fails the form). */
    private function autoThumbnail(Request $request): ?UploadedFile
    {
        $file = $request->file('auto_thumbnail');

        if (! $file instanceof UploadedFile || ! $file->isValid() || (int) $file->getSize() > self::THUMBNAIL_MAX_KB * 1024) {
            return null;
        }

        $info = @getimagesize((string) $file->getRealPath());

        return $info !== false && in_array($info[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            ? $file
            : null;
    }

    /**
     * Select options for the lesson form.
     *
     * @return array<string,mixed>
     */
    private function formOptions(User $user, ?LearningVideo $video): array
    {
        $manager = $user->hasPermission('learning.manage');

        $categories = LearningCategory::query()->orderBy('position')->orderBy('name')->get(['id', 'name']);

        $courses = LearningCourse::query()
            ->when(! $manager, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('instructor_id', $user->id)
                ->when($video?->learning_course_id, fn (Builder $w) => $w->orWhere('id', $video->learning_course_id))))
            ->orderBy('title')
            ->get(['id', 'learning_category_id', 'title', 'status']);

        $topics = $courses->isEmpty()
            ? collect()
            : LearningTopic::query()
                ->whereIn('learning_course_id', $courses->pluck('id'))
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'learning_course_id', 'title']);

        $instructors = $manager ? $this->instructorOptions($video) : collect();

        return [
            'categories' => $categories,
            'courses' => $courses,
            'topics' => $topics,
            'instructors' => $instructors,
            'canPickInstructor' => $manager,
            'formConfig' => [
                'courses' => $courses->map(fn (LearningCourse $c) => [
                    'id' => $c->id,
                    'category_id' => $c->learning_category_id,
                    'title' => $c->title.($c->status === 'published' ? '' : ' (draft)'),
                ])->values(),
                'topics' => $topics->map(fn (LearningTopic $t) => [
                    'id' => $t->id,
                    'course_id' => $t->learning_course_id,
                    'title' => $t->title,
                ])->values(),
                'categoryId' => old('learning_category_id', $video?->learning_category_id),
                'courseId' => old('learning_course_id', $video?->learning_course_id),
                'topicId' => old('learning_topic_id', $video?->learning_topic_id),
                'tags' => $this->oldTags($video),
            ],
        ];
    }

    private function instructorOptions(?LearningVideo $video): Collection
    {
        return User::query()
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w
                    ->where('status', 'active')
                    ->where(fn (Builder $r) => $r->where('can_teach', true)->orWhere('is_admin', true)))
                ->when($video?->instructor_id, fn (Builder $w) => $w->orWhere('id', $video->instructor_id)))
            ->orderBy('name')
            ->get(['id', 'name', 'can_teach', 'is_admin']);
    }

    /** @return list<string> */
    private function oldTags(?LearningVideo $video): array
    {
        $old = old('tags');

        if ($old !== null) {
            return array_values(array_filter(array_map('trim', explode(',', (string) $old)), fn ($t) => $t !== ''));
        }

        return array_values($video?->tags ?? []);
    }

    /**
     * learnChunkUploader() config.
     *
     * @param  array{token:string,name:string}|null  $existing  a finished upload to show again after a failed submit
     * @return array<string,mixed>
     */
    private function uploaderConfig(string $purpose, bool $autoThumbnail, bool $captureMeta, bool $required = false, ?array $existing = null): array
    {
        $placeholder = str_repeat('T', 40); // satisfies the route's token pattern
        $tokenUrl = fn (string $name) => str_replace($placeholder, '__TOKEN__', route($name, ['token' => $placeholder]));

        $limits = app(ChunkedUploadService::class)->limits();
        $rules = $limits['purposes'][$purpose] ?? ['extensions' => [], 'max_bytes' => 0];

        return [
            'configUrl' => route('studio.uploads.config'),
            'initUrl' => route('studio.uploads.init'),
            'chunkUrl' => $tokenUrl('studio.uploads.chunk'),
            'completeUrl' => $tokenUrl('studio.uploads.complete'),
            'abortUrl' => $tokenUrl('studio.uploads.abort'),
            'purpose' => $purpose,
            'accept' => implode(',', array_merge(
                array_map(fn ($ext) => '.'.$ext, $rules['extensions']),
                $rules['mimes'] ?? [],
            )),
            'autoThumbnail' => $autoThumbnail,
            'captureMeta' => $captureMeta,
            'required' => $required,
            'maxBytes' => (int) $rules['max_bytes'],
            'extensions' => $rules['extensions'],
            'existing' => $existing,
        ];
    }

    /** A finished upload from the previous (failed) submit, so the file is not uploaded twice. */
    private function pendingUpload(Request $request): ?array
    {
        $token = old('upload_token');

        if (! is_string($token) || preg_match('/\A[A-Za-z0-9]{40}\z/', $token) !== 1) {
            return null;
        }

        try {
            $upload = app(ChunkedUploadService::class)->resolveCompleted($request->user(), $token, 'video');
        } catch (ValidationException) {
            return null;
        }

        return [
            'token' => $token,
            'name' => $upload['original_name'],
            'size' => $upload['size'],
            'duration_seconds' => old('duration_seconds'),
            'width' => old('width'),
            'height' => old('height'),
        ];
    }

    private function backToVideo(LearningVideo $video): RedirectResponse
    {
        return redirect()->to(url()->previous(route('studio.videos.edit', $video)));
    }
}
