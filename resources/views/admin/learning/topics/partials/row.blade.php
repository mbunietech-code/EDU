{{-- One topic row with inline edit + delete. Vars: $topic (videos_count loaded), $impactLines, $showCourse, $canManage --}}
@php($useOld = $errors->any() && (int) old('_topic') === $topic->id)
<li x-data="{ editing: {{ $useOld ? 'true' : 'false' }} }" class="px-4 py-4 md:px-6">
    <div x-show="!editing" class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600" title="Position">{{ $topic->position }}</span>
        <div class="min-w-0 flex-1">
            <p class="font-medium text-gray-900">{{ $topic->title }}</p>
            <p class="text-xs text-gray-500">
                @if ($showCourse && $topic->course)
                    <a href="{{ route('admin.learning.courses.show', [$topic->course, 'tab' => 'topics']) }}" class="hover:text-indigo-600">{{ $topic->course->title }}</a> ·
                @endif
                {{ $topic->videos_count }} {{ Str::plural('lesson', $topic->videos_count) }}
                @if ($topic->description) · {{ Str::limit($topic->description, 120) }}@endif
            </p>
        </div>
        @if ($canManage)
            <div class="flex items-center gap-1">
                <button type="button" @click="editing = true" class="rounded p-1.5 text-gray-400 hover:bg-indigo-50 hover:text-indigo-600" title="Edit">
                    <span class="sr-only">Edit topic {{ $topic->title }}</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                </button>
                <x-learning.confirm-delete :action="route('admin.learning.topics.destroy', $topic)"
                    :title="'Delete the topic “'.$topic->title.'”?'" :impact="$impactLines"
                    :button-label="'Delete topic '.$topic->title" />
            </div>
        @endif
    </div>

    @if ($canManage)
        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.learning.topics.update', $topic) }}" class="grid grid-cols-1 gap-3 md:grid-cols-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="_topic" value="{{ $topic->id }}">
            <div class="md:col-span-3">
                <label for="topic-title-{{ $topic->id }}" class="mbui-label">Title</label>
                <input id="topic-title-{{ $topic->id }}" name="title" type="text" required maxlength="255" value="{{ $useOld ? old('title') : $topic->title }}" class="mbui-input mt-1 w-full">
            </div>
            <div class="md:col-span-2">
                <label for="topic-desc-{{ $topic->id }}" class="mbui-label">Description</label>
                <input id="topic-desc-{{ $topic->id }}" name="description" type="text" maxlength="500" value="{{ $useOld ? old('description') : $topic->description }}" class="mbui-input mt-1 w-full">
            </div>
            <div>
                <label for="topic-pos-{{ $topic->id }}" class="mbui-label">Position</label>
                <input id="topic-pos-{{ $topic->id }}" name="position" type="number" min="0" max="65535" value="{{ $useOld ? old('position') : $topic->position }}" class="mbui-input mt-1 w-full">
            </div>
            <div class="flex justify-end gap-2 md:col-span-6">
                <x-mbui.button variant="secondary" @click="editing = false">Cancel</x-mbui.button>
                <x-mbui.button type="submit">Save</x-mbui.button>
            </div>
        </form>
    @endif
</li>
