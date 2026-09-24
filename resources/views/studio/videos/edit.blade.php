<x-layouts.app :title="'Edit: '.$video->title" header="Teaching Studio">

    {{-- The admin layout has no [x-cloak] rule; keep hidden Alpine parts hidden until Alpine starts. --}}
    <style>[x-cloak]{display:none !important;}</style>

    <div class="mbui-page-header">
        <div class="min-w-0">
            <nav class="mb-1 text-sm text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('studio.videos.index') }}" class="mbui-anchor">Video lessons</a>
                <span aria-hidden="true">/</span>
                <span>Edit</span>
            </nav>
            <h1 class="mbui-title break-words">{{ $video->title }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                <x-mbui.status-badge :status="$video->status" />
                @unless ($video->hasFile())
                    <x-mbui.badge appearance="warning">No video file</x-mbui.badge>
                @endunless
                @if ($video->course?->trashed())
                    <x-mbui.badge appearance="danger">Course in trash</x-mbui.badge>
                @endif
                <span>{{ \App\Models\LearningVideo::VISIBILITY[$video->visibility] ?? $video->visibility }}</span>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-mbui.btn-link :href="route('learn.videos.show', $video)" variant="secondary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                View as learner
            </x-mbui.btn-link>
            @can('publish', $video)
                @if ($video->isPublished())
                    <form method="POST" action="{{ route('studio.videos.unpublish', $video) }}">
                        @csrf
                        <x-mbui.button type="submit" variant="secondary">Unpublish</x-mbui.button>
                    </form>
                @elseif ($video->hasFile())
                    <form method="POST" action="{{ route('studio.videos.publish', $video) }}" x-data="{ notify: true }" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="notify" :value="notify ? 1 : 0" value="1">
                        @if ($video->published_at === null)
                            <label class="hidden items-center gap-1.5 text-sm text-gray-600 sm:inline-flex">
                                <input type="checkbox" x-model="notify" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Notify learners
                            </label>
                        @endif
                        <x-mbui.button type="submit" variant="success">Publish</x-mbui.button>
                    </form>
                @else
                    <x-mbui.button variant="success" disabled class="cursor-not-allowed opacity-50" title="Upload a video file first">Publish</x-mbui.button>
                @endif
            @endcan
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Details --}}
            <form method="POST" action="{{ route('studio.videos.update', $video) }}" enctype="multipart/form-data"
                x-data="learnVideoForm(@js($formConfig))">
                @csrf
                @method('PUT')
                <x-mbui.card>
                    <h2 class="text-base font-semibold text-gray-900">Details</h2>
                    <div class="mt-4">
                        @include('studio.videos.partials._form')
                    </div>
                    <div class="mt-6 border-t border-gray-100 pt-6">
                        @include('studio.videos.partials._settings')
                    </div>
                    <div class="mt-6 flex justify-end">
                        <x-mbui.button type="submit">Save changes</x-mbui.button>
                    </div>
                </x-mbui.card>
            </form>

            {{-- Replace the file --}}
            <x-mbui.card id="replace" class="scroll-mt-24">
                <h2 class="text-base font-semibold text-gray-900">{{ $video->hasFile() ? 'Replace video' : 'Upload the video' }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($video->hasFile())
                        The current file is deleted once the new one is saved. Learner progress, comments and resources are kept.
                    @else
                        This lesson has no video yet — upload it to be able to publish.
                    @endif
                </p>
                <form method="POST" action="{{ route('studio.videos.replace', $video) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="_panel" value="replace">
                    @include('studio.videos.partials.uploader', [
                        'config' => $replaceUploader,
                        'label' => $video->hasFile() ? 'New video file' : 'Video file',
                        'inputId' => 'replace-file',
                        'panel' => 'replace',
                    ])
                    <div class="flex justify-end">
                        <x-mbui.button type="submit" class="disabled:cursor-not-allowed disabled:opacity-50">
                            {{ $video->hasFile() ? 'Replace video' : 'Attach video' }}
                        </x-mbui.button>
                    </div>
                </form>
            </x-mbui.card>

            @include('studio.videos.partials.renditions')
            @include('studio.videos.partials.resources')

            {{-- Danger zone --}}
            @can('delete', $video)
                <div class="mbui-card border border-red-200 p-6">
                    <h2 class="text-base font-semibold text-red-700">Danger zone</h2>
                    <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-600">
                            Move this lesson to the trash. Learners lose access immediately; an administrator can restore it
                            within {{ (int) config('learning.trash_retention_days', 30) }} days.
                        </p>
                        <x-learning.confirm-delete :action="route('studio.videos.destroy', $video)"
                            :title="'Delete “'.$video->title.'”?'" :impact="$impact" button-label="Delete lesson">
                            <x-slot:trigger>
                                <x-mbui.button variant="danger" class="w-full sm:w-auto">Delete lesson</x-mbui.button>
                            </x-slot:trigger>
                        </x-learning.confirm-delete>
                    </div>
                </div>
            @endcan
        </div>

        {{-- Summary --}}
        <div class="min-w-0 space-y-6">
            <x-mbui.card class="p-0 overflow-hidden">
                <div class="aspect-video bg-gray-100">
                    <x-learning.thumb :src="$video->thumbnailUrl()" :alt="$video->title" />
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between gap-3 px-5 py-3">
                        <dt class="text-gray-500">File</dt>
                        <dd class="min-w-0 truncate text-right text-gray-900" title="{{ $video->original_name }}">{{ $video->original_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-3">
                        <dt class="text-gray-500">Size</dt>
                        <dd class="text-gray-900">{{ $video->sizeLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-3">
                        <dt class="text-gray-500">Duration</dt>
                        <dd class="text-gray-900">{{ $video->durationLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-3">
                        <dt class="text-gray-500">Resolution</dt>
                        <dd class="text-gray-900">
                            @if ($video->width && $video->height)
                                {{ $video->width }}×{{ $video->height }} ({{ $video->sourceQualityLabel() }})
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-3">
                        <dt class="text-gray-500">Extra qualities</dt>
                        <dd class="text-gray-900">{{ $video->renditions->pluck('quality')->implode(', ') ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-3">
                        <dt class="text-gray-500">Views</dt>
                        <dd class="text-gray-900">{{ number_format((int) $video->views) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-3">
                        <dt class="text-gray-500">Instructor</dt>
                        <dd class="text-gray-900">{{ $video->instructor->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-3">
                        <dt class="text-gray-500">Published</dt>
                        <dd class="text-gray-900">{{ $video->published_at?->format('d M Y, H:i') ?? 'Never' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-3">
                        <dt class="text-gray-500">Created</dt>
                        <dd class="text-gray-900">{{ $video->created_at?->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-3">
                        <dt class="text-gray-500">Last updated</dt>
                        <dd class="text-gray-900">{{ $video->updated_at?->diffForHumans() }}</dd>
                    </div>
                </dl>
            </x-mbui.card>

            <x-mbui.card>
                <h2 class="mbui-section-label">On this page</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="#replace" class="mbui-anchor">{{ $video->hasFile() ? 'Replace video' : 'Upload the video' }}</a></li>
                    <li><a href="#renditions" class="mbui-anchor">Qualities ({{ $video->renditions->count() }})</a></li>
                    <li><a href="#resources" class="mbui-anchor">Resources ({{ $video->resources->count() }})</a></li>
                </ul>
            </x-mbui.card>
        </div>
    </div>
</x-layouts.app>
