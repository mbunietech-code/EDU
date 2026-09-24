<x-layouts.admin :title="$course->title" header="Learning">

    <div class="mbui-page-header">
        <div class="min-w-0">
            <nav class="text-xs text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('admin.learning.courses.index') }}" class="hover:text-indigo-600">Courses</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.learning.courses.index', ['category' => $course->learning_category_id]) }}" class="hover:text-indigo-600">{{ $course->category?->name ?? 'No category' }}</a>
            </nav>
            <h1 class="mbui-title">{{ $course->title }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                <x-mbui.status-badge :status="$course->status" />
                <x-mbui.badge :appearance="$course->isOpen() ? 'neutral' : 'info'">{{ $course->accessLabel() }}</x-mbui.badge>
                <span>{{ $course->levelLabel() }}</span>
                <span aria-hidden="true">·</span>
                <span>Instructor: {{ $course->instructor?->name ?? 'none' }}</span>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-mbui.btn-link :href="route('learn.courses.show', $course->slug)" variant="secondary">View as learner</x-mbui.btn-link>
            @if ($canManage)
                <x-mbui.btn-link :href="route('admin.learning.courses.edit', $course)">Edit</x-mbui.btn-link>
                <x-learning.confirm-delete :action="route('admin.learning.courses.destroy', $course)"
                    :title="'Delete the course “'.$course->title.'”?'" :impact="$impact" button-label="Delete course">
                    <x-slot:trigger>
                        <x-mbui.button variant="secondary" class="text-red-700">Delete</x-mbui.button>
                    </x-slot:trigger>
                    @if ($stats->videos_count > 0)
                        <label class="mt-4 flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                            <input type="checkbox" name="with_videos" value="1" class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-600">
                            <span>Also delete the {{ $stats->videos_count }} {{ Str::plural('lesson', $stats->videos_count) }} in this course. Without this, the delete is blocked until the lessons are moved.</span>
                        </label>
                    @endif
                    <p class="mt-3 text-xs text-gray-500">The course goes to the trash and can be restored for {{ (int) config('learning.trash_retention_days', 30) }} days.</p>
                </x-learning.confirm-delete>
            @endif
        </div>
    </div>

    <nav class="mt-6 flex gap-1 overflow-x-auto border-b border-gray-200 text-sm" aria-label="Course sections">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.learning.courses.show', [$course, 'tab' => $key]) }}"
                @if ($tab === $key) aria-current="page" @endif
                class="whitespace-nowrap border-b-2 px-3 py-2 font-medium {{ $tab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
                @if ($key === 'topics')<span class="ml-1 text-xs text-gray-400">{{ $stats->topics_count }}</span>@endif
                @if ($key === 'lessons')<span class="ml-1 text-xs text-gray-400">{{ $stats->videos_count }}</span>@endif
                @if ($key === 'enrolments')<span class="ml-1 text-xs text-gray-400">{{ $stats->enrollments_count }}</span>@endif
            </a>
        @endforeach
    </nav>

    {{-- ======================= Overview ======================= --}}
    @if ($tab === 'overview')
        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-mbui.stats-card title="Lessons" :value="number_format($stats->videos_count)" :trend="number_format($stats->published_videos_count).' published'" />
            <x-mbui.stats-card title="Enrolled" :value="number_format($stats->enrollments_count)" :trend="number_format($stats->completed_enrollments_count).' completed the course'" />
            <x-mbui.stats-card title="Learners started" :value="number_format($stats->started_learners)" :trend="number_format($stats->completed_lessons_count).' lessons completed'" />
            <x-mbui.stats-card title="Views" :value="number_format((int) $stats->views_total)" :trend="$stats->topics_count.' '.Str::plural('topic', $stats->topics_count)" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-mbui.card class="lg:col-span-2">
                <h2 class="mbui-section-label">About this course</h2>
                @if ($course->summary)
                    <p class="mt-3 text-sm text-gray-700">{{ $course->summary }}</p>
                @endif
                @if ($course->description)
                    <div class="prose prose-sm mt-3 max-w-none text-gray-700">{!! $course->descriptionHtml() !!}</div>
                @elseif (! $course->summary)
                    <p class="mt-3 text-sm text-gray-500">No description yet.
                        @if ($canManage)<a href="{{ route('admin.learning.courses.edit', $course) }}" class="mbui-anchor">Add one</a>.@endif
                    </p>
                @endif
            </x-mbui.card>
            <x-mbui.card>
                <h2 class="mbui-section-label">Completion</h2>
                <x-learning.progress-bar class="mt-4" size="md" :percent="$stats->average_completion" label="Average completion (learners who started)" />
                @php
                    $enrolledPct = $stats->enrollments_count > 0 ? round($stats->completed_enrollments_count / $stats->enrollments_count * 100) : 0;
                @endphp
                <x-learning.progress-bar class="mt-4" size="md" :percent="$enrolledPct" label="Enrolled learners who finished" />
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Created</dt><dd class="text-gray-900">{{ $course->created_at?->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Published</dt><dd class="text-gray-900">{{ $course->published_at?->format('d M Y') ?? 'Not yet' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Position</dt><dd class="text-gray-900">{{ $course->position }}</dd></div>
                </dl>
            </x-mbui.card>
        </div>
    @endif

    {{-- ======================= Topics ======================= --}}
    @if ($tab === 'topics')
        @if ($canManage)
            <x-mbui.card class="mt-6">
                <h2 class="text-base font-semibold text-gray-900">Add a topic</h2>
                <p class="text-sm text-gray-500">Topics are chapters inside the course; lessons are grouped under them.</p>
                <form method="POST" action="{{ route('admin.learning.topics.store') }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-6">
                    @csrf
                    <input type="hidden" name="learning_course_id" value="{{ $course->id }}">
                    <div class="md:col-span-3">
                        <label for="topic-title" class="mbui-label">Title <span class="text-red-600">*</span></label>
                        <input id="topic-title" name="title" type="text" required maxlength="255" class="mbui-input mt-1 w-full">
                    </div>
                    <div class="md:col-span-2">
                        <label for="topic-description" class="mbui-label">Description</label>
                        <input id="topic-description" name="description" type="text" maxlength="500" class="mbui-input mt-1 w-full">
                    </div>
                    <div>
                        <label for="topic-position" class="mbui-label">Position</label>
                        <input id="topic-position" name="position" type="number" min="0" max="65535" placeholder="Auto" class="mbui-input mt-1 w-full">
                    </div>
                    <div class="flex justify-end md:col-span-6"><x-mbui.button type="submit">Add topic</x-mbui.button></div>
                </form>
            </x-mbui.card>
        @endif

        @if ($topics->isEmpty())
            <x-learning.empty class="mt-6" title="No topics yet" message="Lessons without a topic are listed under “Other lessons”." />
        @else
            <x-mbui.card class="mt-6 overflow-hidden p-0">
                <ul class="divide-y divide-gray-100">
                    @foreach ($topics as $topic)
                        @include('admin.learning.topics.partials.row', ['topic' => $topic, 'impactLines' => $topicImpact[$topic->id] ?? [], 'showCourse' => false])
                    @endforeach
                </ul>
            </x-mbui.card>
        @endif
    @endif

    {{-- ======================= Lessons ======================= --}}
    @if ($tab === 'lessons')
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500">Lessons are uploaded and edited in the Teaching Studio.</p>
            @if (auth()->user()->canUploadLessons())
                <x-mbui.btn-link :href="route('studio.videos.create')" variant="secondary">Add video</x-mbui.btn-link>
            @endif
        </div>

        @if ($videosByTopic->isEmpty())
            <x-learning.empty class="mt-4" title="No lessons in this course yet"
                message="Add a video in the Teaching Studio and choose this course."
                :action-href="auth()->user()->canUploadLessons() ? route('studio.videos.create') : null"
                :action-label="auth()->user()->canUploadLessons() ? 'Add video' : null" />
        @else
            @php
                $groups = $topics->map(fn ($t) => ['title' => $t->title, 'videos' => $videosByTopic->get($t->id, collect())])
                    ->push(['title' => 'Other lessons', 'videos' => $videosByTopic->get(0, collect())])
                    ->filter(fn ($g) => $g['videos']->isNotEmpty());
            @endphp
            <div class="mt-4 space-y-4">
                @foreach ($groups as $group)
                    <x-mbui.card class="overflow-hidden p-0">
                        <h3 class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-900 sm:px-6">{{ $group['title'] }}
                            <span class="font-normal text-gray-500">· {{ $group['videos']->count() }} {{ Str::plural('lesson', $group['videos']->count()) }}</span></h3>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($group['videos'] as $video)
                                <li class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $canManage ? route('studio.videos.edit', $video) : route('learn.videos.show', $video->slug) }}" class="block truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $video->title }}</a>
                                        <p class="text-xs text-gray-500">{{ $video->durationLabel() }} · {{ $video->instructor?->name ?? 'No instructor' }} · {{ number_format($video->views) }} views</p>
                                    </div>
                                    <x-mbui.status-badge :status="$video->status" />
                                    @if (! $video->hasFile())
                                        <x-mbui.badge appearance="warning">No video file</x-mbui.badge>
                                    @endif
                                    @if ($canManage)
                                        <x-mbui.icon-link :href="route('studio.videos.edit', $video)" :label="'Edit '.$video->title" />
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-mbui.card>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ======================= Enrolments ======================= --}}
    @if ($tab === 'enrolments')
        @if ($canManage)
            <x-mbui.card class="mt-6">
                <h2 class="text-base font-semibold text-gray-900">Enrol members</h2>
                <p class="text-sm text-gray-500">
                    @if ($course->isOpen())
                        This course is open to all members; enrolling adds it to their “My courses” list.
                    @else
                        Only enrolled learners can watch this course.
                    @endif
                </p>
                <form method="POST" action="{{ route('admin.learning.enrollments.store', $course) }}" class="mt-4 space-y-3">
                    @csrf
                    <div x-data="learnUserPicker(@js(['searchUrl' => route('studio.users.search'), 'name' => 'user_ids[]', 'selected' => [], 'multiple' => true, 'label' => 'Search members to enrol']))"></div>
                    @error('user_ids')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <div class="flex justify-end"><x-mbui.button type="submit">Enrol selected</x-mbui.button></div>
                </form>
            </x-mbui.card>
        @endif

        @if ($enrollments->isEmpty())
            <x-learning.empty class="mt-6" title="No enrolments yet"
                :message="$course->isOpen() ? 'Members enrol themselves from the course page, or you can add them above.' : 'Add learners above so they can watch this course.'" />
        @else
            <x-mbui.card class="mt-6 overflow-hidden p-0">
                <div class="hidden grid-cols-12 gap-4 border-b border-gray-200 bg-gray-50 px-6 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 md:grid">
                    <div class="col-span-4">Learner</div>
                    <div class="col-span-2">Source</div>
                    <div class="col-span-3">Progress</div>
                    <div class="col-span-2">Enrolled</div>
                    <div class="col-span-1"><span class="sr-only">Actions</span></div>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($enrollments as $enrollment)
                        @php($p = $enrollmentProgress[$enrollment->user_id] ?? ['percent' => 0, 'completed' => 0, 'total' => 0, 'started' => false])
                        <li class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-4 md:grid-cols-12 md:items-center md:px-6">
                            <div class="col-span-2 min-w-0 md:col-span-4">
                                @if ($enrollment->user)
                                    <a href="{{ route('admin.learning.progress.show', $enrollment->user) }}" class="block truncate font-medium text-gray-900 hover:text-indigo-600">{{ $enrollment->user->name }}</a>
                                    <p class="truncate text-xs text-gray-500">{{ $enrollment->user->email }}</p>
                                @else
                                    <span class="text-gray-400">Deleted member</span>
                                @endif
                            </div>
                            <div class="text-sm md:col-span-2">
                                <x-mbui.badge :appearance="$enrollment->source === 'admin' ? 'info' : 'neutral'">{{ $enrollment->source === 'admin' ? 'Added by staff' : 'Self-enrolled' }}</x-mbui.badge>
                                @if ($enrollment->enroller)<p class="mt-1 truncate text-xs text-gray-500">by {{ $enrollment->enroller->name }}</p>@endif
                            </div>
                            <div class="col-span-2 md:col-span-3">
                                <x-learning.progress-bar :percent="$p['percent']" :label="$enrollment->completed_at ? 'Completed' : ($p['started'] ? $p['completed'].' of '.$p['total'].' lessons' : 'Not started')" />
                            </div>
                            <div class="text-xs text-gray-500 md:col-span-2">
                                <span class="md:hidden">Enrolled </span>{{ ($enrollment->enrolled_at ?? $enrollment->created_at)?->format('d M Y') }}
                            </div>
                            <div class="flex justify-end md:col-span-1">
                                @if ($canManage)
                                    <x-learning.confirm-delete :action="route('admin.learning.enrollments.destroy', [$course, $enrollment])"
                                        :title="'Remove '.($enrollment->user?->name ?? 'this learner').' from the course?'"
                                        :impact="['Their lesson progress is kept', $course->isOpen() ? 'They can still watch (the course is open)' : 'They lose access to this course\'s lessons']"
                                        :button-label="'Remove '.($enrollment->user?->name ?? 'learner')" />
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-mbui.card>
            <div class="mt-4">{{ $enrollments->links() }}</div>
        @endif
    @endif
</x-layouts.admin>
