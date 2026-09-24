{{-- Actions for one lesson in the studio list. Expects: $video, $impact (list<string>), optional $mobile. --}}
@php($mobile = $mobile ?? false)

<div class="flex items-center {{ $mobile ? 'justify-between' : 'justify-end' }} gap-1">
    <div class="flex items-center gap-1">
        <x-mbui.icon-link :href="route('learn.videos.show', $video)" icon="eye" :label="'View “'.$video->title.'” as a learner'" />

        @can('update', $video)
            <x-mbui.icon-link :href="route('studio.videos.edit', $video)" icon="pencil" :label="'Edit “'.$video->title.'”'" />
            <a href="{{ route('studio.videos.edit', $video) }}#replace" title="Replace video file"
                class="inline-flex rounded p-1.5 text-gray-400 hover:bg-indigo-50 hover:text-indigo-600">
                <span class="sr-only">Replace the video file of “{{ $video->title }}”</span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
            </a>
        @endcan
    </div>

    <div class="flex items-center gap-1">
        @can('publish', $video)
            @if ($video->isPublished())
                <form method="POST" action="{{ route('studio.videos.unpublish', $video) }}">
                    @csrf
                    <button type="submit" class="rounded-md px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50"
                        aria-label="Unpublish “{{ $video->title }}”">Unpublish</button>
                </form>
            @elseif ($video->hasFile())
                <form method="POST" action="{{ route('studio.videos.publish', $video) }}">
                    @csrf
                    <input type="hidden" name="notify" value="1">
                    <button type="submit" class="rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                        aria-label="Publish “{{ $video->title }}”"
                        title="{{ $video->published_at ? 'Publish again' : 'Publish and notify eligible learners' }}">Publish</button>
                </form>
            @else
                <span class="cursor-not-allowed rounded-md px-2 py-1 text-xs font-semibold text-gray-300" title="Upload a video file before publishing">Publish</span>
            @endif
        @endcan

        @can('delete', $video)
            <x-learning.confirm-delete :action="route('studio.videos.destroy', $video)"
                :title="'Delete “'.$video->title.'”?'" :impact="$impact"
                :button-label="'Delete “'.$video->title.'”'" />
        @endcan
    </div>
</div>
