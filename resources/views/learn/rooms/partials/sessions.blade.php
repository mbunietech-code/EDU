{{-- Past (ended) sessions of a room: date and length. --}}
<section class="mbui-card p-5" aria-labelledby="room-sessions">
    <h2 id="room-sessions" class="text-base font-semibold text-gray-900">Past sessions</h2>

    @if ($pastSessions->isEmpty())
        <p class="mt-2 text-sm text-gray-500">This class has not run yet.</p>
    @else
        <ul class="mt-3 divide-y divide-gray-100 text-sm" role="list">
            @foreach ($pastSessions as $session)
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="min-w-0 truncate text-gray-900">
                        @if ($session->started_at)
                            <time datetime="{{ $session->started_at->toIso8601String() }}">{{ $session->started_at->format('D, d M Y · H:i') }}</time>
                        @else
                            —
                        @endif
                    </span>
                    <span class="shrink-0 text-gray-500">{{ $durationLabel($session->durationSeconds()) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
