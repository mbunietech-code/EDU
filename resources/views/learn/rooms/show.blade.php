@php
    /** @var \App\Models\LearningRoom $room */
    $ends = $room->endsAt();
    $durationLabel = function (int $seconds): string {
        if ($seconds <= 0) {
            return '—';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h > 0 ? $h.' h '.$m.' min' : max(1, $m).' min';
    };
    $minutes = (int) $room->duration_minutes;
    $plannedLabel = $minutes >= 60
        ? intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '')
        : $minutes.' min';
@endphp

<x-layouts.app :title="$room->title" header="Learning">

    <nav class="mb-4 flex min-w-0 items-center gap-1.5 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route('learn.rooms.index') }}" class="mbui-anchor shrink-0">Live classes</a>
        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
        <span class="truncate text-gray-700">{{ $room->title }}</span>
    </nav>

    @if ($room->isCancelled())
        <x-mbui.alert type="error" class="mb-4" role="status">
            <p class="font-semibold">This class was cancelled.</p>
            @if ($room->cancel_reason)
                <p class="mt-1">Reason: {{ $room->cancel_reason }}</p>
            @endif
        </x-mbui.alert>
    @elseif ($room->isDraft())
        <x-mbui.alert type="warning" class="mb-4">
            <strong class="font-semibold">Draft:</strong> only you and room staff can see this class until it is scheduled.
        </x-mbui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Hero --}}
            <section class="mbui-card p-5 sm:p-6" aria-labelledby="room-title">
                <div class="flex flex-wrap items-center gap-2">
                    <x-learning.room-status :status="$room->status" />
                    @if ($room->category)
                        <x-mbui.badge appearance="info">{{ $room->category->name }}</x-mbui.badge>
                    @endif
                    <x-mbui.badge>{{ $room->accessLabel() }}</x-mbui.badge>
                </div>

                <h1 id="room-title" class="mt-3 break-words text-xl font-semibold text-gray-900 sm:text-2xl">{{ $room->title }}</h1>

                @if ($room->host)
                    <p class="mt-1 text-sm text-gray-600">
                        Hosted by
                        @if ($room->host->isInstructor())
                            <a href="{{ route('learn.instructors.show', $room->host) }}" class="mbui-anchor">{{ $room->host->name }}</a>
                        @else
                            <span class="font-medium text-gray-800">{{ $room->host->name }}</span>
                        @endif
                    </p>
                @endif

                @if ($room->isLive())
                    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <span class="font-semibold">The class is live now.</span>
                        <span>{{ $liveCount }} {{ Str::plural('person', $liveCount) }} in the room</span>
                        @if ($room->started_at)
                            <span class="text-red-700">· started {{ $room->started_at->format('H:i') }}</span>
                        @endif
                    </div>
                @endif

                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    @if ($room->isLive())
                        <x-mbui.btn-link :href="route('learn.rooms.live', $room)" variant="danger">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            Join live class
                        </x-mbui.btn-link>
                    @elseif ($room->isScheduled())
                        <x-mbui.btn-link :href="route('learn.rooms.live', $room)" variant="secondary">Open classroom</x-mbui.btn-link>
                    @endif

                    @if ($room->scheduled_at && in_array($room->status, ['scheduled', 'live', 'draft'], true))
                        <x-mbui.btn-link :href="route('learn.rooms.ics', $room)" variant="secondary">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m-3.75-9v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25A2.25 2.25 0 0118.75 21H5.25A2.25 2.25 0 013 18.75z" />
                            </svg>
                            Add to calendar
                        </x-mbui.btn-link>
                    @endif
                </div>

                @if ($isManager)
                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <p class="mbui-section-label">Host tools</p>
                        <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                            @if ($canStart)
                                <form method="POST" action="{{ route('studio.rooms.start', $room->id) }}">
                                    @csrf
                                    <x-mbui.button type="submit" variant="success" class="w-full sm:w-auto">
                                        <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                                        </svg>
                                        {{ $room->status === 'completed' ? 'Start a new session' : 'Start session' }}
                                    </x-mbui.button>
                                </form>
                            @endif
                            <x-mbui.btn-link :href="route('studio.rooms.show', $room->id)" variant="secondary">Manage in studio</x-mbui.btn-link>
                        </div>
                    </div>
                @endif
            </section>

            {{-- About --}}
            <section class="mbui-card p-5 sm:p-6" aria-labelledby="room-about">
                <h2 id="room-about" class="text-base font-semibold text-gray-900">About this class</h2>
                @if ($descriptionHtml)
                    <div class="research-prose mt-3 break-words text-sm text-gray-700">{!! $descriptionHtml !!}</div>
                @else
                    <p class="mt-2 text-sm text-gray-500">The host has not added a description.</p>
                @endif
            </section>

            @include('learn.rooms.partials.recordings')
        </div>

        <aside class="min-w-0 space-y-6">
            <section class="mbui-card p-5" aria-labelledby="room-details">
                <h2 id="room-details" class="text-base font-semibold text-gray-900">Details</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Date</dt>
                        <dd class="font-medium text-gray-900">
                            @if ($room->scheduled_at)
                                <time datetime="{{ $room->scheduled_at->toIso8601String() }}">{{ $room->scheduled_at->format('l, d M Y') }}</time>
                            @else
                                Not scheduled yet
                            @endif
                        </dd>
                    </div>
                    @if ($room->scheduled_at)
                        <div>
                            <dt class="text-gray-500">Time</dt>
                            <dd class="font-medium text-gray-900">
                                {{ $room->scheduled_at->format('H:i') }}@if ($ends) – {{ $ends->format('H:i') }}@endif
                                <span class="font-normal text-gray-500">({{ config('app.timezone') }})</span>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500">Duration</dt>
                        <dd class="font-medium text-gray-900">{{ $plannedLabel }}</dd>
                    </div>
                    @if ($ends)
                        <div>
                            <dt class="text-gray-500">Ends</dt>
                            <dd class="font-medium text-gray-900">{{ $ends->format('D, d M Y · H:i') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500">Who can join</dt>
                        <dd class="font-medium text-gray-900">{{ $room->accessLabel() }}</dd>
                    </div>
                    @if ($room->category)
                        <div>
                            <dt class="text-gray-500">Category</dt>
                            <dd><a href="{{ route('learn.categories.show', $room->category) }}" class="mbui-anchor">{{ $room->category->name }}</a></dd>
                        </div>
                    @endif
                    @if ($room->course)
                        <div>
                            <dt class="text-gray-500">Course</dt>
                            <dd>
                                @if ($courseVisible)
                                    <a href="{{ route('learn.courses.show', $room->course) }}" class="mbui-anchor">{{ $room->course->title }}</a>
                                @else
                                    <span class="font-medium text-gray-900">{{ $room->course->title }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($room->topic)
                        <div>
                            <dt class="text-gray-500">Topic</dt>
                            <dd class="font-medium text-gray-900">{{ $room->topic->title }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500">In-class chat</dt>
                        <dd class="font-medium text-gray-900">
                            {{ $room->chat_enabled ? 'Chat on' : 'Chat off' }} · {{ $room->questions_enabled ? 'Questions on' : 'Questions off' }}
                        </dd>
                    </div>
                </dl>
            </section>

            @include('learn.rooms.partials.sessions')
        </aside>
    </div>
</x-layouts.app>
