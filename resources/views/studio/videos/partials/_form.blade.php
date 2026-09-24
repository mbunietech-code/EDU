{{--
    Lesson details (title, description, placement, tags). Must sit inside an
    element with x-data="learnVideoForm(@js($formConfig))".
    Expects: $video, $categories, $courses, $canPickInstructor.
--}}
<div class="space-y-5">
    <div>
        <label for="video-title" class="mbui-label">Title <span class="text-red-600">*</span></label>
        <input id="video-title" name="title" type="text" required maxlength="255" autocomplete="off"
            value="{{ old('title', $video->title) }}" class="mbui-input mt-1 w-full"
            placeholder="e.g. Introduction to cell biology">
        @if (old('_panel') !== 'resource')
            @error('title')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        @endif
    </div>

    <div>
        <label for="video-description" class="mbui-label">Description</label>
        <textarea id="video-description" name="description" rows="6" maxlength="20000"
            class="mbui-input mt-1 w-full" placeholder="What will learners get from this lesson?">{{ old('description', $video->description) }}</textarea>
        <p class="mt-1 text-xs text-gray-500">
            Markdown is supported: <code class="rounded bg-gray-100 px-1">**bold**</code>, <code class="rounded bg-gray-100 px-1">*italic*</code>,
            lists (<code class="rounded bg-gray-100 px-1">- item</code>) and links (<code class="rounded bg-gray-100 px-1">[text](https://…)</code>).
        </p>
        @error('description')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="video-category" class="mbui-label">Category <span class="text-red-600">*</span></label>
            <select id="video-category" name="learning_category_id" x-model="categoryId" class="mbui-input mt-1 w-full">
                <option value="">Choose a category…</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <p x-show="courseForcesCategory" x-cloak class="mt-1 text-xs text-gray-500">Set by the selected course.</p>
            @error('learning_category_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            @if ($categories->isEmpty())
                <p class="mt-1 text-xs text-amber-700">No categories exist yet. Ask a content manager to create one.</p>
            @endif
        </div>

        <div>
            <label for="video-course" class="mbui-label">Course</label>
            <select id="video-course" name="learning_course_id" x-model="courseId" class="mbui-input mt-1 w-full">
                <option value="">Standalone lesson (no course)</option>
                <template x-for="c in filteredCourses" :key="c.id">
                    <option :value="String(c.id)" x-text="c.title"></option>
                </template>
            </select>
            @if (! $canPickInstructor)
                <p class="mt-1 text-xs text-gray-500">
                    @if ($courses->isEmpty())
                        You are not the instructor of any course yet, so this lesson will be standalone in its category.
                    @else
                        You can add lessons only to courses you teach.
                    @endif
                </p>
            @endif
            @error('learning_course_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="video-topic" class="mbui-label">Topic</label>
        <select id="video-topic" name="learning_topic_id" x-model="topicId" class="mbui-input mt-1 w-full"
            :disabled="!courseId || !filteredTopics.length" :class="(!courseId || !filteredTopics.length) && 'bg-gray-50 text-gray-400'">
            <option value="" x-text="!courseId ? 'Choose a course first' : (filteredTopics.length ? 'No topic' : 'This course has no topics')"></option>
            <template x-for="t in filteredTopics" :key="t.id">
                <option :value="String(t.id)" x-text="t.title"></option>
            </template>
        </select>
        @error('learning_topic_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="video-tags" class="mbui-label">Tags</label>
        <input type="hidden" name="tags" :value="tagsValue" value="{{ old('tags', implode(', ', $video->tags ?? [])) }}">
        <div class="mt-1 flex flex-wrap items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2 py-1.5 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500"
            @click="$refs.tagInput.focus()">
            <template x-for="(tag, i) in tags" :key="tag">
                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 py-0.5 pl-2.5 pr-1 text-xs font-medium text-indigo-700">
                    <span x-text="tag"></span>
                    <button type="button" class="rounded-full p-0.5 hover:bg-indigo-100" @click.stop="removeTag(i)" :aria-label="'Remove tag ' + tag">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </span>
            </template>
            <input id="video-tags" x-ref="tagInput" type="text" x-model="tagInput" autocomplete="off" maxlength="200"
                @keydown="onTagKeydown($event)" @paste="onTagPaste($event)" @blur="addTag()"
                class="min-w-[8rem] flex-1 border-0 p-1 text-sm focus:ring-0" placeholder="Add a tag and press Enter">
        </div>
        <p class="mt-1 text-xs text-gray-500" x-show="!tagError">Up to 15 short keywords learners can search for.</p>
        <p class="mt-1 text-xs text-red-700" x-show="tagError" x-cloak x-text="tagError"></p>
        @error('tags')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
