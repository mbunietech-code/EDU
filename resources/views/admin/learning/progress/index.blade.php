<x-layouts.admin title="Learner progress" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Learner progress</h1>
            <p class="mt-1 text-sm text-gray-500">Who is learning, how far they got, and how each course is doing.</p>
        </div>
    </div>

    <nav class="mt-6 flex gap-1 border-b border-gray-200 text-sm" aria-label="Progress views">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.learning.progress.index', ['tab' => $key]) }}"
                @if ($tab === $key) aria-current="page" @endif
                class="border-b-2 px-3 py-2 font-medium {{ $tab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.learning.progress.index') }}" class="mt-4 flex max-w-md gap-2" role="search">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label for="progress-q" class="sr-only">{{ $tab === 'courses' ? 'Search courses' : 'Search learners' }}</label>
        <input id="progress-q" type="search" name="q" value="{{ $search }}" placeholder="{{ $tab === 'courses' ? 'Search courses…' : 'Search by name or email…' }}" class="mbui-input w-full">
        <x-mbui.button type="submit" variant="secondary">Search</x-mbui.button>
    </form>

    @if ($tab === 'learners')
        @if ($learners->isEmpty())
            <x-learning.empty class="mt-4" :title="$search !== '' ? 'No learners match your search' : 'No learning activity yet'"
                message="Members appear here once they enrol in a course or start a lesson." />
        @else
            <x-mbui.card class="mt-4 hidden overflow-hidden p-0 md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="mbui-th">Learner</th>
                                <th class="mbui-th text-right">Enrolled</th>
                                <th class="mbui-th text-right">Lessons started</th>
                                <th class="mbui-th text-right">Completed</th>
                                <th class="mbui-th text-right">Watch time</th>
                                <th class="mbui-th">Last activity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($learners as $learner)
                                <tr class="hover:bg-gray-50">
                                    <td class="mbui-td">
                                        <a href="{{ route('admin.learning.progress.show', $learner) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $learner->name }}</a>
                                        <span class="block text-xs text-gray-500">{{ $learner->email }}</span>
                                    </td>
                                    <td class="mbui-td text-right text-sm">{{ number_format($learner->enrolled_count) }}</td>
                                    <td class="mbui-td text-right text-sm">{{ number_format($learner->started_count) }}</td>
                                    <td class="mbui-td text-right text-sm">{{ number_format($learner->completed_count) }}</td>
                                    <td class="mbui-td text-right text-sm">{{ \App\Services\Learning\LearningAnalytics::duration($learner->watch_seconds) }}</td>
                                    <td class="mbui-td text-sm text-gray-500">{{ $learner->last_activity_at ? \Illuminate\Support\Carbon::parse($learner->last_activity_at)->diffForHumans() : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-mbui.card>
            <ul class="mt-4 space-y-3 md:hidden">
                @foreach ($learners as $learner)
                    <li class="mbui-card p-4">
                        <a href="{{ route('admin.learning.progress.show', $learner) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $learner->name }}</a>
                        <p class="truncate text-xs text-gray-500">{{ $learner->email }}</p>
                        <dl class="mt-3 grid grid-cols-4 gap-2 text-center text-xs text-gray-500">
                            <div><dt>Enrolled</dt><dd class="text-sm font-semibold text-gray-900">{{ $learner->enrolled_count }}</dd></div>
                            <div><dt>Started</dt><dd class="text-sm font-semibold text-gray-900">{{ $learner->started_count }}</dd></div>
                            <div><dt>Done</dt><dd class="text-sm font-semibold text-gray-900">{{ $learner->completed_count }}</dd></div>
                            <div><dt>Watched</dt><dd class="text-sm font-semibold text-gray-900">{{ \App\Services\Learning\LearningAnalytics::duration($learner->watch_seconds) }}</dd></div>
                        </dl>
                        <p class="mt-2 text-xs text-gray-500">Last activity: {{ $learner->last_activity_at ? \Illuminate\Support\Carbon::parse($learner->last_activity_at)->diffForHumans() : '—' }}</p>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $learners->links() }}</div>
        @endif
    @else
        @if ($courses->isEmpty())
            <x-learning.empty class="mt-4" :title="$search !== '' ? 'No courses match your search' : 'No courses yet'"
                message="Course progress is shown once courses exist." />
        @else
            <x-mbui.card class="mt-4 hidden overflow-hidden p-0 md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="mbui-th">Course</th>
                                <th class="mbui-th text-right">Enrolled</th>
                                <th class="mbui-th text-right">Started</th>
                                <th class="mbui-th text-right">Completed</th>
                                <th class="mbui-th w-48">Average completion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($courses as $course)
                                <tr class="hover:bg-gray-50">
                                    <td class="mbui-td">
                                        <a href="{{ route('admin.learning.courses.show', [$course, 'tab' => 'enrolments']) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $course->title }}</a>
                                        <span class="block text-xs text-gray-500">{{ $course->category?->name ?? '—' }} · {{ $course->published_videos_count }} published {{ Str::plural('lesson', $course->published_videos_count) }}</span>
                                    </td>
                                    <td class="mbui-td text-right text-sm">{{ number_format($course->enrollments_count) }}</td>
                                    <td class="mbui-td text-right text-sm">{{ number_format($course->started_learners) }}</td>
                                    <td class="mbui-td text-right text-sm">{{ number_format($course->completed_enrollments_count) }}</td>
                                    <td class="mbui-td"><x-learning.progress-bar :percent="$course->average_completion" :label="$course->average_completion.'%'" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-mbui.card>
            <ul class="mt-4 space-y-3 md:hidden">
                @foreach ($courses as $course)
                    <li class="mbui-card p-4">
                        <a href="{{ route('admin.learning.courses.show', [$course, 'tab' => 'enrolments']) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $course->title }}</a>
                        <p class="text-xs text-gray-500">{{ $course->category?->name ?? '—' }}</p>
                        <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs text-gray-500">
                            <div><dt>Enrolled</dt><dd class="text-sm font-semibold text-gray-900">{{ $course->enrollments_count }}</dd></div>
                            <div><dt>Started</dt><dd class="text-sm font-semibold text-gray-900">{{ $course->started_learners }}</dd></div>
                            <div><dt>Completed</dt><dd class="text-sm font-semibold text-gray-900">{{ $course->completed_enrollments_count }}</dd></div>
                        </dl>
                        <x-learning.progress-bar class="mt-3" :percent="$course->average_completion" label="Average completion" />
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $courses->links() }}</div>
        @endif
    @endif
</x-layouts.admin>
