{{-- Downloadable resources / links of a lesson. Expects: $video (resources loaded), $resourceExtensions, $maxResourceMb. --}}
@php
    $resourceTab = old('_panel') === 'resource' ? old('type', 'file') : 'file';
@endphp

<x-mbui.card id="resources" class="scroll-mt-24">
    <h2 class="text-base font-semibold text-gray-900">Resources</h2>
    <p class="mt-1 text-sm text-gray-500">Slides, worksheets or useful links shown under the video.</p>

    @if ($video->resources->isEmpty())
        <div class="mt-4 rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
            No resources yet.
        </div>
    @else
        <ul class="mt-4 divide-y divide-gray-100 rounded-lg border border-gray-200">
            @foreach ($video->resources as $resource)
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $resource->isLink() ? 'bg-sky-50 text-sky-600' : 'bg-indigo-50 text-indigo-600' }}" aria-hidden="true">
                            @if ($resource->isLink())
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                            @else
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $resource->title }}</p>
                            <p class="truncate text-xs text-gray-500">
                                @if ($resource->isLink())
                                    <a href="{{ $resource->url }}" target="_blank" rel="noopener noreferrer nofollow" class="mbui-anchor">{{ $resource->url }}</a>
                                @else
                                    {{ $resource->original_name }} · {{ $resource->sizeLabel() }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <x-learning.confirm-delete :action="route('studio.videos.resources.destroy', [$video, $resource])"
                        :title="'Remove “'.$resource->title.'”?'"
                        :impact="[$resource->isFile() ? 'The file ('.$resource->sizeLabel().') is deleted' : 'The link is removed from the lesson']"
                        :button-label="'Remove '.$resource->title" />
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('studio.videos.resources.store', $video) }}" enctype="multipart/form-data"
        x-data="{ type: @js($resourceTab) }" class="mt-6 space-y-4 rounded-lg bg-gray-50 p-4">
        @csrf
        <input type="hidden" name="_panel" value="resource">
        <h3 class="text-sm font-semibold text-gray-900">Add a resource</h3>

        <fieldset>
            <legend class="sr-only">Resource type</legend>
            <div class="inline-flex rounded-lg bg-white p-1 ring-1 ring-inset ring-gray-200">
                <label class="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium" :class="type === 'file' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:text-gray-900'">
                    <input type="radio" name="type" value="file" x-model="type" class="sr-only"> File
                </label>
                <label class="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium" :class="type === 'link' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:text-gray-900'">
                    <input type="radio" name="type" value="link" x-model="type" class="sr-only"> Link
                </label>
            </div>
            @error('type')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </fieldset>

        <div>
            <label for="resource-title" class="mbui-label">Title <span class="text-red-600">*</span></label>
            <input id="resource-title" name="title" type="text" required maxlength="255"
                value="{{ old('_panel') === 'resource' ? old('title') : '' }}" class="mbui-input mt-1 w-full" placeholder="e.g. Lecture slides">
            @if (old('_panel') === 'resource')
                @error('title')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            @endif
        </div>

        <div x-show="type === 'file'">
            <label for="resource-file" class="mbui-label">File <span class="text-red-600">*</span></label>
            <input id="resource-file" name="file" type="file" :disabled="type !== 'file'" :required="type === 'file'"
                accept="{{ collect($resourceExtensions)->map(fn ($e) => '.'.$e)->implode(',') }}"
                class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
            <p class="mt-1 text-xs text-gray-500">{{ strtoupper(implode(', ', $resourceExtensions)) }} · up to {{ $maxResourceMb }} MB.</p>
            @error('file')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div x-show="type === 'link'" x-cloak>
            <label for="resource-url" class="mbui-label">Link <span class="text-red-600">*</span></label>
            <input id="resource-url" name="url" type="url" maxlength="500" :disabled="type !== 'link'" :required="type === 'link'"
                value="{{ old('_panel') === 'resource' ? old('url') : '' }}" class="mbui-input mt-1 w-full" placeholder="https://…">
            @error('url')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div class="flex justify-end">
            <x-mbui.button type="submit">Add resource</x-mbui.button>
        </div>
    </form>
</x-mbui.card>
