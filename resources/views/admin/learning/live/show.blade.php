<x-layouts.admin :title="'Live: '.$room->title" header="Learning">
    @php
        $hms = function (int $seconds): string {
            $seconds = max(0, $seconds);

            return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        };
        $isLive = $room->isLive() && $session && $session->isOpen();
        $rightLabels = ['audio' => 'Mic', 'video' => 'Camera', 'screen' => 'Screen'];
    @endphp

    <div class="mbui-page-header">
        <div class="min-w-0">
            <a href="{{ route('admin.learning.live.index') }}" class="mbui-anchor text-sm">&larr; Live sessions</a>
            <h1 class="mbui-title mt-1 truncate">{{ $room->title }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                <x-learning.room-status :status="$room->status" />
                <span>Host: {{ $room->host?->name ?? '—' }}</span>
                @if ($session)
                    <span>· Started {{ $session->started_at?->format('D, d M Y · H:i') }}</span>
                    <span>· {{ $hms($session->durationSeconds()) }}</span>
                @endif
                @if ($room->is_locked)<x-mbui.badge appearance="neutral">Locked</x-mbui.badge>@endif
                @if ($session?->isRecording())<x-mbui.badge appearance="danger">Recording</x-mbui.badge>@endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($isLive)
                <x-mbui.btn-link :href="route('learn.rooms.live', $room)" variant="secondary">Open classroom</x-mbui.btn-link>
            @endif
            <x-mbui.btn-link :href="route('studio.rooms.show', $room->id)" variant="ghost">Room in studio</x-mbui.btn-link>
        </div>
    </div>

    @if (! $session)
        <x-learning.empty class="mt-6" title="This room has not had a session yet" message="Sessions appear here once the host starts the room." />
    @else
        {{-- Moderation --}}
        @if ($isLive && $canModerate)
            <x-mbui.card class="mt-6">
                <h2 class="text-base font-semibold text-gray-900">Moderation</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('studio.rooms.lock', $room->id) }}">
                        @csrf
                        <input type="hidden" name="locked" value="{{ $room->is_locked ? 0 : 1 }}">
                        <x-mbui.button type="submit" variant="secondary">{{ $room->is_locked ? 'Unlock room' : 'Lock room' }}</x-mbui.button>
                    </form>
                    <form method="POST" action="{{ route('studio.rooms.mute-all', $room->id) }}">
                        @csrf
                        <input type="hidden" name="kind" value="audio">
                        <x-mbui.button type="submit" variant="secondary">Mute everyone</x-mbui.button>
                    </form>
                    <form method="POST" action="{{ route('studio.rooms.media', $room->id) }}">
                        @csrf
                        <input type="hidden" name="allow_participant_media" value="{{ $room->allow_participant_media ? 0 : 1 }}">
                        <x-mbui.button type="submit" variant="secondary">{{ $room->allow_participant_media ? 'Turn off participant mic & camera' : 'Allow participant mic & camera' }}</x-mbui.button>
                    </form>
                    <form method="POST" action="{{ route('studio.rooms.media', $room->id) }}">
                        @csrf
                        <input type="hidden" name="allow_screen_share" value="{{ $room->allow_screen_share ? 0 : 1 }}">
                        <x-mbui.button type="submit" variant="secondary">{{ $room->allow_screen_share ? 'Stop participant screen sharing' : 'Allow participant screen sharing' }}</x-mbui.button>
                    </form>
                    <form method="POST" action="{{ route('studio.rooms.end', $room->id) }}"
                        onsubmit="return confirm('End this class for everyone? Everyone is disconnected and attendance is saved.')">
                        @csrf
                        <x-mbui.button type="submit" variant="danger">End class for everyone</x-mbui.button>
                    </form>
                </div>
            </x-mbui.card>
        @endif

        {{-- Participants --}}
        <h2 class="mbui-section-label mt-8">{{ $isLive ? 'People in this session' : 'People in the last session' }}</h2>
        @if ($attendances->isEmpty())
            <x-learning.empty class="mt-3" title="Nobody joined yet" message="People appear here when they join the call." />
        @else
            <x-mbui.card class="mt-3 overflow-hidden p-0">
                <ul class="divide-y divide-gray-100">
                    @foreach ($attendances as $attendance)
                        @php
                            $present = $attendance->isPresent();
                            $rightsNow = $rights[$attendance->id] ?? null;
                        @endphp
                        <li class="flex flex-col gap-2 px-4 py-3 md:flex-row md:items-center md:px-6">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ $attendance->user?->name ?? 'Former member' }}
                                    @if ($attendance->role === 'host')<x-mbui.badge appearance="info" class="ml-1">Host</x-mbui.badge>@endif
                                </p>
                                <p class="truncate text-xs text-gray-500">
                                    {{ $attendance->user?->email }}
                                    · joined {{ $attendance->first_joined_at?->format('H:i') ?? '—' }}
                                    · {{ $hms((int) $attendance->total_seconds) }} in class
                                    · {{ $attendance->join_count }} {{ Str::plural('join', $attendance->join_count) }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if ($attendance->removed_at)
                                    <x-mbui.badge appearance="danger">Removed{{ $attendance->remover ? ' by '.$attendance->remover->name : '' }}</x-mbui.badge>
                                @elseif ($present)
                                    <x-mbui.badge appearance="success">In class</x-mbui.badge>
                                @else
                                    <x-mbui.badge appearance="neutral">Left {{ $attendance->left_at?->format('H:i') }}</x-mbui.badge>
                                @endif
                                @if ($rightsNow && $attendance->role !== 'host')
                                    @foreach ($rightLabels as $key => $label)
                                        <x-mbui.badge :appearance="$rightsNow[$key] ? 'success' : 'neutral'" :title="$label.($rightsNow[$key] ? ' allowed' : ' not allowed')">{{ $label }}{{ $rightsNow[$key] ? '' : ' ✕' }}</x-mbui.badge>
                                    @endforeach
                                @endif
                            </div>
                            @if ($isLive && $canModerate && $present && $attendance->role !== 'host' && ! $attendance->removed_at && $attendance->user)
                                <div class="flex flex-wrap gap-1.5">
                                    <form method="POST" action="{{ route('studio.rooms.participants.mute', ['room' => $room->id, 'user' => $attendance->user_id]) }}">
                                        @csrf
                                        <input type="hidden" name="kind" value="audio">
                                        <x-mbui.button type="submit" variant="ghost" class="px-2 py-1 text-xs">Mute</x-mbui.button>
                                    </form>
                                    <form method="POST" action="{{ route('studio.rooms.participants.permissions', ['room' => $room->id, 'user' => $attendance->user_id]) }}">
                                        @csrf
                                        <input type="hidden" name="audio" value="{{ $rightsNow && $rightsNow['audio'] ? 0 : 1 }}">
                                        <x-mbui.button type="submit" variant="ghost" class="px-2 py-1 text-xs">{{ $rightsNow && $rightsNow['audio'] ? 'Deny mic' : 'Allow mic' }}</x-mbui.button>
                                    </form>
                                    <form method="POST" action="{{ route('studio.rooms.participants.remove', ['room' => $room->id, 'user' => $attendance->user_id]) }}"
                                        onsubmit="return confirm({{ \Illuminate\Support\Js::from('Remove '.$attendance->user->name.' from this session? They cannot rejoin it.') }})">
                                        @csrf
                                        <x-mbui.button type="submit" variant="ghost" class="px-2 py-1 text-xs text-red-700">Remove</x-mbui.button>
                                    </form>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-mbui.card>
        @endif

        {{-- Chat moderation --}}
        <h2 class="mbui-section-label mt-8">Chat, questions &amp; announcements</h2>
        @if (! $messages || $messages->isEmpty())
            <x-learning.empty class="mt-3" title="No messages in this session" message="Chat, questions and announcements from the class appear here." />
        @else
            <x-mbui.card class="mt-3 overflow-hidden p-0">
                <ul class="divide-y divide-gray-100">
                    @foreach ($messages as $message)
                        <li class="flex items-start gap-3 px-4 py-3 md:px-6">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-500">
                                    <span class="font-medium text-gray-900">{{ $message->user?->name ?? 'Former member' }}</span>
                                    · {{ $message->created_at?->format('H:i') }}
                                    · {{ ucfirst($message->type) }}
                                    @if ($message->type === 'question' && $message->is_answered) · answered @endif
                                </p>
                                @if ($message->is_deleted)
                                    <p class="mt-0.5 text-sm italic text-gray-400">Removed: {{ Str::limit($message->body, 200) }}</p>
                                @else
                                    <p class="mt-0.5 whitespace-pre-line break-words text-sm text-gray-800">{{ $message->body }}</p>
                                @endif
                            </div>
                            @if ($canModerate && ! $message->is_deleted)
                                <form method="POST" action="{{ route('admin.learning.live.messages.destroy', ['room' => $room->id, 'message' => $message->id]) }}"
                                    onsubmit="return confirm('Remove this message for everyone?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove message">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-mbui.card>
            <div class="mt-4">{{ $messages->links() }}</div>
        @endif
    @endif
</x-layouts.admin>
