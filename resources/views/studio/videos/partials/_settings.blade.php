{{--
    Lesson settings: visibility, instructor, thumbnail.
    Expects: $video, $canPickInstructor, $instructors, and $autoThumbnailHint (bool, create page).
--}}
@php
    $currentVisibility = old('visibility', $video->visibility ?? 'course');
    $autoThumbnailHint = $autoThumbnailHint ?? false;
@endphp

<div class="space-y-5">
    <fieldset>
        <legend class="mbui-label">Who can watch</legend>
        <div class="mt-2 space-y-2">
            @foreach (\App\Models\LearningVideo::VISIBILITY as $value => $label)
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                    <input type="radio" name="visibility" value="{{ $value }}" class="mt-0.5 text-indigo-600 focus:ring-indigo-500"
                        @checked($currentVisibility === $value)>
                    <span class="text-sm">
                        <span class="block font-medium text-gray-900">{{ $label }}</span>
                        <span class="block text-xs text-gray-500">
                            @switch($value)
                                @case('course') Anyone who can open the course (or every member for a standalone lesson). @break
                                @case('members') Every signed-in member, even without enrolling — good for previews. @break
                                @default Only you, the course instructor and learning staff.
                            @endswitch
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('visibility')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </fieldset>

    <div>
        <label for="video-instructor" class="mbui-label">Instructor</label>
        @if ($canPickInstructor)
            @php($currentInstructor = (string) old('instructor_id', $video->exists ? $video->instructor_id : (auth()->user()->isInstructor() ? auth()->id() : '')))
            <select id="video-instructor" name="instructor_id" class="mbui-input mt-1 w-full">
                <option value="">No instructor</option>
                @foreach ($instructors as $person)
                    <option value="{{ $person->id }}" @selected($currentInstructor === (string) $person->id)>
                        {{ $person->name }}{{ $person->can_teach ? '' : ' (administrator)' }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Only members with instructor access or administrators can be chosen.</p>
            @error('instructor_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        @else
            <p id="video-instructor" class="mt-1 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                {{ $video->exists ? ($video->instructor->name ?? '—') : auth()->user()->name.' (you)' }}
            </p>
        @endif
    </div>

    <div>
        <label for="video-thumbnail" class="mbui-label">Thumbnail</label>
        @if ($video->exists && $video->thumbnail_path)
            <div class="mt-2 flex items-start gap-3">
                <div class="aspect-video w-32 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                    <x-learning.thumb :src="$video->thumbnailUrl()" alt="Current thumbnail" />
                </div>
                <x-learning.confirm-delete :action="route('studio.videos.thumbnail.destroy', $video)"
                    title="Remove the thumbnail?" :impact="['Learners will see a plain placeholder until you add a new one']"
                    button-label="Remove thumbnail">
                    <x-slot:trigger>
                        <button type="button" class="text-sm font-medium text-red-600 hover:text-red-500">Remove</button>
                    </x-slot:trigger>
                </x-learning.confirm-delete>
            </div>
        @endif
        <input id="video-thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
            class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
        <p class="mt-1 text-xs text-gray-500">
            JPG, PNG, WebP or GIF up to 5 MB, ideally 16:9.
            @if ($autoThumbnailHint)
                Leave empty to use a frame captured from the video automatically.
            @elseif ($video->exists && $video->thumbnail_path)
                Choosing a file replaces the current thumbnail.
            @endif
        </p>
        @error('thumbnail')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
