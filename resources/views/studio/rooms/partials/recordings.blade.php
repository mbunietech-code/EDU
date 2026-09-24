{{--
    Recordings of a room (#recordings). Expects: $room, $recordings (with session, uploader, video),
    $recordingImpacts, $sessions, $canManage, $canCreateLessons, $uploader (config|null),
    $providerName, $supportsRecording, $hms (closure).
--}}
@php
    $tz = (string) config('app.timezone');
    $statusBadge = ['ready' => 'success', 'processing' => 'info', 'failed' => 'danger'];
@endphp

<x-mbui.card id="recordings" class="scroll-mt-20">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="mbui-section-label">Recordings</h2>
        <span class="text-xs text-gray-500">{{ $recordings->count() }} {{ \Illuminate\Support\Str::plural('recording', $recordings->count()) }}</span>
    </div>

    <p class="mt-2 text-sm text-gray-600">
        @if ($supportsRecording && $providerName === 'jaas')
            Recordings you start in the live room are imported here automatically a few minutes after they stop.
        @elseif ($supportsRecording)
            Recordings made on the video server can be uploaded here when the file is ready.
        @else
            The live provider does not record on the server. Record with your own software (e.g. OBS) and upload the file below.
        @endif
        Shared recordings can be watched by everyone who can see the room.
    </p>

    @if ($recordings->isEmpty())
        <x-mbui.empty-state title="No recordings yet" :message="$canManage ? 'Upload a recording of a session so learners can catch up.' : 'Recordings appear here once the host adds them.'" />
    @else
        <ul class="mt-4 divide-y divide-gray-100 rounded-lg border border-gray-200">
            @foreach ($recordings as $recording)
                <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $recording->original_name ?: 'Recording #'.$recording->id }}</p>
                            <x-mbui.badge :appearance="$statusBadge[$recording->status] ?? 'neutral'">{{ ucfirst($recording->status) }}</x-mbui.badge>
                            @if ($recording->is_shared)
                                <x-mbui.badge appearance="info">Shared</x-mbui.badge>
                            @endif
                            @if ($recording->video)
                                <x-mbui.badge appearance="success">Published as lesson</x-mbui.badge>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $recording->source === 'jaas' ? 'Cloud recording' : 'Uploaded' }}{{ $recording->uploader ? ' by '.$recording->uploader->name : '' }}
                            · {{ $recording->created_at?->copy()->setTimezone($tz)->format('d M Y H:i') }}
                            @if ($recording->session?->started_at) · session of {{ $recording->session->started_at->copy()->setTimezone($tz)->format('d M Y H:i') }}@endif
                            @if ($recording->path) · {{ $recording->sizeLabel() }}@endif
                            @if ($recording->duration_seconds) · {{ $hms((int) $recording->duration_seconds) }}@endif
                        </p>
                        @if ($recording->status === 'failed' && $recording->error)
                            <p class="mt-1 text-xs text-red-700">{{ \Illuminate\Support\Str::limit($recording->error, 200) }}</p>
                        @endif
                        @if ($recording->video)
                            <p class="mt-1 text-xs text-gray-600">
                                The file now belongs to the lesson
                                @if (! $recording->video->trashed() && auth()->user()->can('update', $recording->video))
                                    <a href="{{ route('studio.videos.edit', $recording->video) }}" class="mbui-anchor">“{{ $recording->video->title }}”</a>.
                                @else
                                    “{{ $recording->video->title }}”@if ($recording->video->trashed()) (in trash)@endif.
                                @endif
                            </p>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-1">
                        @if ($recording->isReady() && $recording->path)
                            <a href="{{ route('learn.rooms.recordings.stream', [$room, $recording]) }}" target="_blank" rel="noopener"
                                class="rounded-md px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                                aria-label="Watch {{ $recording->original_name ?: 'recording' }} in a new tab">Watch</a>
                        @endif

                        @if ($canManage)
                            <form method="POST" action="{{ route('studio.rooms.recordings.update', [$room, $recording]) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="is_shared" value="{{ $recording->is_shared ? 0 : 1 }}">
                                <button type="submit" class="rounded-md px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-100"
                                    aria-label="{{ $recording->is_shared ? 'Stop sharing this recording with learners' : 'Share this recording with learners' }}">
                                    {{ $recording->is_shared ? 'Unshare' : 'Share with learners' }}
                                </button>
                            </form>

                            @if ($canCreateLessons && $recording->isReady() && $recording->path && ! $recording->learning_video_id)
                                <form method="POST" action="{{ route('studio.rooms.recordings.publish', [$room, $recording]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                        title="Create a draft video lesson from this recording (the file is moved, not copied)">Publish as lesson</button>
                                </form>
                            @endif

                            <x-learning.confirm-delete :action="route('studio.rooms.recordings.destroy', [$room, $recording])"
                                title="Delete this recording?" :impact="$recordingImpacts[$recording->id] ?? []"
                                button-label="Delete recording" />
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canManage && $uploader)
        <form method="POST" action="{{ route('studio.rooms.recordings.store', $room) }}" class="mt-6 space-y-4 border-t border-gray-100 pt-5">
            @csrf
            <h3 class="text-sm font-semibold text-gray-900">Upload a recording</h3>

            @include('studio.videos.partials.uploader', [
                'config' => $uploader,
                'label' => 'Recording file',
                'inputId' => 'recording-file',
                'help' => 'The file stays private to room staff until you share it or publish it as a lesson.',
            ])

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="recording-session" class="mbui-label">Session</label>
                    <select id="recording-session" name="learning_room_session_id" class="mbui-input mt-1 w-full">
                        <option value="">Not linked to a session</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}" @selected((string) old('learning_room_session_id') === (string) $session->id)>
                                {{ $session->started_at?->copy()->setTimezone($tz)->format('D, d M Y · H:i') }}
                            </option>
                        @endforeach
                    </select>
                    @error('learning_room_session_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-3 sm:mt-6">
                    <input type="checkbox" name="is_shared" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_shared'))>
                    <span class="text-sm">
                        <span class="block font-medium text-gray-900">Share with learners</span>
                        <span class="block text-xs text-gray-500">Everyone who can see the room can watch it.</span>
                    </span>
                </label>
            </div>

            <div class="flex justify-end">
                <x-mbui.button type="submit" class="w-full disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Save recording</x-mbui.button>
            </div>
        </form>
    @endif
</x-mbui.card>
