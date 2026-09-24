@php
    $editing = $course->exists;
    $firstPublish = ! $editing || $course->published_at === null;
@endphp
<x-layouts.admin :title="$editing ? 'Edit course' : 'New course'" header="Learning">

    <div class="mbui-page-header">
        <div>
            <nav class="text-xs text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('admin.learning.courses.index') }}" class="hover:text-indigo-600">Courses</a>
                @if ($editing)
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('admin.learning.courses.show', $course) }}" class="hover:text-indigo-600">{{ Str::limit($course->title, 60) }}</a>
                @endif
            </nav>
            <h1 class="mbui-title">{{ $editing ? 'Edit course' : 'New course' }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $editing ? 'Update the details learners see and who may take the course.' : 'Courses group lessons under a category. Add lessons from the Teaching Studio once the course exists.' }}</p>
        </div>
    </div>

    @if ($categories->isEmpty())
        <x-learning.empty class="mt-6" title="Create a category first"
            message="Every course belongs to a category."
            :action-href="route('admin.learning.categories.index')" action-label="Manage categories" />
    @else
        <form method="POST" enctype="multipart/form-data"
            action="{{ $editing ? route('admin.learning.courses.update', $course) : route('admin.learning.courses.store') }}"
            x-data="{ status: @js(old('status', $course->status ?? 'draft')), removeThumb: {{ old('remove_thumbnail') ? 'true' : 'false' }}, preview: null }"
            class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="space-y-6 lg:col-span-2">
                <x-mbui.card>
                    <h2 class="mbui-section-label">Details</h2>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="title" class="mbui-label">Title <span class="text-red-600">*</span></label>
                            <input id="title" name="title" type="text" required maxlength="255" value="{{ old('title', $course->title) }}" class="mbui-input mt-1 w-full">
                            @error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="summary" class="mbui-label">Summary <span class="font-normal text-gray-400">(shown on course cards)</span></label>
                            <textarea id="summary" name="summary" rows="2" maxlength="1000" class="mbui-input mt-1 w-full">{{ old('summary', $course->summary) }}</textarea>
                            @error('summary')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="description" class="mbui-label">Description</label>
                            <textarea id="description" name="description" rows="10" maxlength="20000" class="mbui-input mt-1 w-full font-mono text-sm">{{ old('description', $course->description) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Markdown is supported: **bold**, *italic*, lists, links. HTML is shown as plain text.</p>
                            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </x-mbui.card>

                <x-mbui.card>
                    <h2 class="mbui-section-label">Thumbnail</h2>
                    <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="aspect-video w-full overflow-hidden rounded-lg bg-gray-100 sm:w-56">
                            <template x-if="preview"><img :src="preview" alt="New thumbnail preview" class="h-full w-full object-cover"></template>
                            <div x-show="!preview" class="h-full w-full" :class="removeThumb ? 'opacity-40' : ''">
                                <x-learning.thumb :src="$course->thumbnailUrl()" :alt="$course->title ? 'Current thumbnail for '.$course->title : 'No thumbnail'" />
                            </div>
                        </div>
                        <div class="min-w-0 flex-1 space-y-3">
                            <div>
                                <label for="thumbnail" class="mbui-label">Upload image</label>
                                <input id="thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
                                    class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                    @change="const f = $event.target.files[0]; preview = f ? URL.createObjectURL(f) : null; if (f) removeThumb = false">
                                <p class="mt-1 text-xs text-gray-500">JPEG, PNG, WebP or GIF up to 5 MB. Resized to 1280 px wide; 16:9 works best.</p>
                                @error('thumbnail')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            @if ($course->thumbnail_path)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="remove_thumbnail" value="1" x-model="removeThumb" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                                    Remove the current thumbnail
                                </label>
                            @endif
                        </div>
                    </div>
                </x-mbui.card>
            </div>

            <div class="space-y-6">
                <x-mbui.card>
                    <h2 class="mbui-section-label">Publishing</h2>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="status" class="mbui-label">Status</label>
                            <select id="status" name="status" x-model="status" class="mbui-input mt-1 w-full">
                                @foreach (\App\Models\LearningCourse::STATUSES as $status)
                                    <option value="{{ $status }}" @selected(old('status', $course->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Drafts are only visible to staff and the course instructor.</p>
                        </div>
                        @if ($firstPublish)
                            <label x-show="status === 'published'" x-cloak class="flex items-start gap-2 rounded-lg bg-indigo-50 p-3 text-sm text-indigo-900">
                                <input type="checkbox" name="notify" value="1" @checked(old('notify', true)) class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                                <span>Notify learners that this course is available (in-app and mobile push).</span>
                            </label>
                        @else
                            <p class="text-xs text-gray-500">First published {{ $course->published_at->format('d M Y, H:i') }}.</p>
                        @endif
                        <div>
                            <label for="access" class="mbui-label">Who may watch</label>
                            <select id="access" name="access" class="mbui-input mt-1 w-full">
                                @foreach (\App\Models\LearningCourse::ACCESS as $key => $label)
                                    <option value="{{ $key }}" @selected(old('access', $course->access ?? 'open') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('access')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </x-mbui.card>

                <x-mbui.card>
                    <h2 class="mbui-section-label">Organisation</h2>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="learning_category_id" class="mbui-label">Category <span class="text-red-600">*</span></label>
                            <select id="learning_category_id" name="learning_category_id" required class="mbui-input mt-1 w-full">
                                <option value="">Choose a category…</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((int) old('learning_category_id', $course->learning_category_id) === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('learning_category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="instructor_id" class="mbui-label">Instructor</label>
                            <select id="instructor_id" name="instructor_id" class="mbui-input mt-1 w-full">
                                <option value="">No instructor</option>
                                @foreach ($instructors as $instructor)
                                    <option value="{{ $instructor->id }}" @selected((int) old('instructor_id', $course->instructor_id) === $instructor->id)>{{ $instructor->name }} ({{ $instructor->email }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Members with instructor access, and admins. The instructor may add lessons to this course.
                                <a href="{{ route('admin.learning.instructors.index') }}" class="mbui-anchor">Manage instructors</a></p>
                            @error('instructor_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="level" class="mbui-label">Level</label>
                                <select id="level" name="level" class="mbui-input mt-1 w-full">
                                    <option value="">All levels</option>
                                    @foreach (\App\Models\LearningCourse::LEVELS as $key => $label)
                                        <option value="{{ $key }}" @selected(old('level', $course->level) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="position" class="mbui-label">Position</label>
                                <input id="position" name="position" type="number" min="0" max="65535" value="{{ old('position', $editing ? $course->position : '') }}" placeholder="Auto" class="mbui-input mt-1 w-full">
                            </div>
                        </div>
                    </div>
                </x-mbui.card>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-mbui.btn-link :href="$editing ? route('admin.learning.courses.show', $course) : route('admin.learning.courses.index')" variant="secondary">Cancel</x-mbui.btn-link>
                    <x-mbui.button type="submit">{{ $editing ? 'Save changes' : 'Create course' }}</x-mbui.button>
                </div>
            </div>
        </form>
    @endif
</x-layouts.admin>
