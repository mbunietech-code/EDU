<x-layouts.admin title="Courses" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Courses</h1>
            <p class="mt-1 text-sm text-gray-500">Organise lessons into courses, choose who teaches them and who may watch.</p>
        </div>
        <div class="flex gap-2">
            @if ($canManage)
                <x-mbui.btn-link :href="route('admin.learning.courses.create', array_filter(['category' => $filters['category']]))">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    New course
                </x-mbui.btn-link>
            @endif
        </div>
    </div>

    @include('admin.learning.partials.catalog-tabs', ['active' => 'courses'])

    <form method="GET" action="{{ route('admin.learning.courses.index') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" role="search">
        <div class="lg:col-span-2">
            <label for="course-q" class="sr-only">Search courses</label>
            <input id="course-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search by title or summary…" class="mbui-input w-full">
        </div>
        <div>
            <label for="course-category" class="sr-only">Category</label>
            <select id="course-category" name="category" class="mbui-input w-full" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <label for="course-status" class="sr-only">Status</label>
            <select id="course-status" name="status" class="mbui-input w-full" onchange="this.form.submit()">
                <option value="">Any status</option>
                @foreach (\App\Models\LearningCourse::STATUSES as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <x-mbui.button type="submit" variant="secondary">Filter</x-mbui.button>
        </div>
    </form>

    @if ($courses->isEmpty())
        @php($filtered = $filters['q'] !== '' || $filters['category'] || $filters['status'])
        <x-learning.empty class="mt-6"
            :title="$filtered ? 'No courses match these filters' : 'No courses yet'"
            :message="$filtered ? 'Try a different search or clear the filters.' : 'Create a course to group lessons for learners.'"
            :action-href="$filtered ? route('admin.learning.courses.index') : ($canManage ? route('admin.learning.courses.create') : null)"
            :action-label="$filtered ? 'Clear filters' : ($canManage ? 'New course' : null)" />
    @else
        {{-- Desktop table --}}
        <x-mbui.card class="mt-6 hidden overflow-hidden p-0 md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="mbui-th">Course</th>
                            <th class="mbui-th">Instructor</th>
                            <th class="mbui-th">Status</th>
                            <th class="mbui-th text-right">Lessons</th>
                            <th class="mbui-th text-right">Enrolled</th>
                            <th class="mbui-th w-40">Avg. completion</th>
                            <th class="mbui-th"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($courses as $course)
                            <tr class="hover:bg-gray-50">
                                <td class="mbui-td">
                                    <a href="{{ route('admin.learning.courses.show', $course) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $course->title }}</a>
                                    <span class="block text-xs text-gray-500">{{ $course->category?->name ?? '—' }} · {{ $course->levelLabel() }}</span>
                                </td>
                                <td class="mbui-td text-sm">{{ $course->instructor?->name ?? '—' }}</td>
                                <td class="mbui-td">
                                    <div class="flex flex-wrap gap-1">
                                        <x-mbui.status-badge :status="$course->status" />
                                        <x-mbui.badge :appearance="$course->isOpen() ? 'neutral' : 'info'">{{ $course->isOpen() ? 'Open' : 'Enrolled only' }}</x-mbui.badge>
                                    </div>
                                </td>
                                <td class="mbui-td text-right text-sm">{{ number_format($course->videos_count) }}</td>
                                <td class="mbui-td text-right text-sm">{{ number_format($course->enrollments_count) }}</td>
                                <td class="mbui-td"><x-learning.progress-bar :percent="$course->average_completion" :label="number_format($course->started_learners).' started'" /></td>
                                <td class="mbui-td">
                                    <div class="flex justify-end gap-1">
                                        <x-mbui.icon-link :href="route('admin.learning.courses.show', $course)" icon="eye" :label="'Open '.$course->title" />
                                        @if ($canManage)
                                            <x-mbui.icon-link :href="route('admin.learning.courses.edit', $course)" :label="'Edit '.$course->title" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-mbui.card>

        {{-- Mobile cards --}}
        <ul class="mt-6 space-y-3 md:hidden">
            @foreach ($courses as $course)
                <li class="mbui-card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.learning.courses.show', $course) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $course->title }}</a>
                            <p class="text-xs text-gray-500">{{ $course->category?->name ?? '—' }} · {{ $course->instructor?->name ?? 'No instructor' }}</p>
                        </div>
                        <x-mbui.status-badge :status="$course->status" />
                    </div>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs text-gray-500">
                        <div><dt>Lessons</dt><dd class="text-sm font-semibold text-gray-900">{{ number_format($course->videos_count) }}</dd></div>
                        <div><dt>Enrolled</dt><dd class="text-sm font-semibold text-gray-900">{{ number_format($course->enrollments_count) }}</dd></div>
                        <div><dt>Access</dt><dd class="text-sm font-semibold text-gray-900">{{ $course->isOpen() ? 'Open' : 'Enrolled' }}</dd></div>
                    </dl>
                    <x-learning.progress-bar class="mt-3" :percent="$course->average_completion" label="Average completion" />
                    <div class="mt-3 flex gap-2">
                        <x-mbui.btn-link :href="route('admin.learning.courses.show', $course)" variant="secondary" class="flex-1">Open</x-mbui.btn-link>
                        @if ($canManage)
                            <x-mbui.btn-link :href="route('admin.learning.courses.edit', $course)" variant="ghost" class="flex-1">Edit</x-mbui.btn-link>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $courses->links() }}</div>
    @endif
</x-layouts.admin>
