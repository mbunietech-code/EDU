<x-layouts.admin title="Course topics" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Topics</h1>
            <p class="mt-1 text-sm text-gray-500">Chapters inside courses. Lessons are grouped under a topic on the course page.</p>
        </div>
    </div>

    @include('admin.learning.partials.catalog-tabs', ['active' => 'topics'])

    <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start">
        <form method="GET" action="{{ route('admin.learning.topics.index') }}" class="flex w-full gap-2 lg:w-80">
            <label for="topic-course-filter" class="sr-only">Course</label>
            <select id="topic-course-filter" name="course" class="mbui-input w-full" onchange="this.form.submit()">
                <option value="">All courses</option>
                @foreach ($courses as $c)
                    <option value="{{ $c->id }}" @selected($courseId === $c->id)>{{ $c->title }}</option>
                @endforeach
            </select>
            <noscript><x-mbui.button type="submit" variant="secondary">Filter</x-mbui.button></noscript>
        </form>
    </div>

    @if ($canManage)
        @if ($courses->isEmpty())
            <x-mbui.alert type="info" class="mt-4">Create a course first — every topic belongs to a course.</x-mbui.alert>
        @else
            <x-mbui.card class="mt-4">
                <h2 class="text-base font-semibold text-gray-900">Add a topic</h2>
                <form method="POST" action="{{ route('admin.learning.topics.store') }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-6">
                    @csrf
                    <div class="md:col-span-2">
                        <label for="new-topic-course" class="mbui-label">Course <span class="text-red-600">*</span></label>
                        <select id="new-topic-course" name="learning_course_id" required class="mbui-input mt-1 w-full">
                            <option value="">Choose…</option>
                            @foreach ($courses as $c)
                                <option value="{{ $c->id }}" @selected((int) old('learning_course_id', $courseId) === $c->id)>{{ $c->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="new-topic-title" class="mbui-label">Title <span class="text-red-600">*</span></label>
                        <input id="new-topic-title" name="title" type="text" required maxlength="255" value="{{ old('_topic') ? '' : old('title') }}" class="mbui-input mt-1 w-full">
                    </div>
                    <div>
                        <label for="new-topic-desc" class="mbui-label">Description</label>
                        <input id="new-topic-desc" name="description" type="text" maxlength="500" value="{{ old('_topic') ? '' : old('description') }}" class="mbui-input mt-1 w-full">
                    </div>
                    <div>
                        <label for="new-topic-pos" class="mbui-label">Position</label>
                        <input id="new-topic-pos" name="position" type="number" min="0" max="65535" placeholder="Auto" class="mbui-input mt-1 w-full">
                    </div>
                    <div class="flex justify-end md:col-span-6"><x-mbui.button type="submit">Add topic</x-mbui.button></div>
                </form>
            </x-mbui.card>
        @endif
    @endif

    @if ($topics->isEmpty())
        <x-learning.empty class="mt-6" :title="$courseId ? 'This course has no topics yet' : 'No topics yet'"
            message="Topics help learners follow a course chapter by chapter." />
    @else
        <x-mbui.card class="mt-6 overflow-hidden p-0">
            <ul class="divide-y divide-gray-100">
                @foreach ($topics as $topic)
                    @include('admin.learning.topics.partials.row', ['topic' => $topic, 'impactLines' => $impact[$topic->id] ?? [], 'showCourse' => true])
                @endforeach
            </ul>
        </x-mbui.card>
        <div class="mt-4">{{ $topics->links() }}</div>
    @endif
</x-layouts.admin>
