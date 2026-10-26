{{-- Shared, ready recordings of a room with an inline player (managers also see unshared ones). --}}
<section class="mbui-card p-5 sm:p-6" aria-labelledby="room-recordings">
    <div class="flex items-center justify-between gap-3">
        <h2 id="room-recordings" class="text-base font-semibold text-gray-900">Recordings</h2>
        @if ($recordings->isNotEmpty())
            <span class="text-xs text-gray-500">{{ $recordings->count() }} {{ Str::plural('recording', $recordings->count()) }}</span>
        @endif
    </div>

    @if ($recordings->isEmpty())
        <x-mbui.empty-state title="No recordings yet"
            message="{{ $room->isLive() || $room->isScheduled() ? 'If the host shares a recording after the class, you can watch it here.' : 'No recording has been shared for this class.' }}"
            />
    @else
        <ul class="mt-4 space-y-6" role="list">
            @foreach ($recordings as $recording)
                @php($recordedAt = $recording->session?->started_at ?? $recording->created_at)
                <li class="min-w-0">
                    <div class="overflow-hidden rounded-lg bg-gray-900">
                        <video class="aspect-video w-full" controls preload="metadata" playsinline controlslist="nodownload"
                            src="{{ route('learn.rooms.recordings.stream', [$room, $recording]) }}"
                            aria-label="Recording of {{ $room->title }} from {{ $recordedAt?->format('d M Y') }}">
                            Your browser cannot play this video.
                        </video>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <span class="font-medium text-gray-900">Session of {{ $recordedAt?->format('D, d M Y · H:i') }}</span>
                        @if ($recording->duration_seconds)
                            <span class="text-gray-500">{{ gmdate($recording->duration_seconds >= 3600 ? 'G:i:s' : 'i:s', (int) $recording->duration_seconds) }}</span>
                        @endif
                        @if ($recording->size_bytes)
                            <span class="text-gray-500">{{ $recording->sizeLabel() }}</span>
                        @endif
                        @if ($isManager && ! $recording->is_shared)
                            <x-mbui.badge appearance="warning">Not shared with learners</x-mbui.badge>
                        @endif
                        <a href="{{ route('learn.rooms.recordings.download', [$room, $recording]) }}" download
                            class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900"
                            aria-label="Download the recording from {{ $recordedAt?->format('d M Y') }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Download
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
