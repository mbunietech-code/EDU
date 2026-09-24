<x-layouts.app :title="$room->title" header="Teaching Studio">

    {{-- The admin layout has no [x-cloak] rule; keep hidden Alpine parts hidden until Alpine starts. --}}
    <style>[x-cloak]{display:none !important;}</style>
    @php
        $tz = (string) config('app.timezone');
        $start = $room->scheduled_at?->copy()->setTimezone($tz);
        $hms = fn (int $s) => sprintf('%d:%02d:%02d', intdiv(max(0, $s), 3600), intdiv(max(0, $s) % 3600, 60), max(0, $s) % 60);
        $pageErrors = collect(['status', 'scheduled_date', 'recording', 'user', 'reason', 'is_shared', 'message', 'upload_token'])
            ->flatMap(fn ($key) => $errors->get($key))
            ->unique();
    @endphp

    <div class="mbui-page-header">
        <div class="min-w-0">
            <nav class="mb-1 text-sm text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('studio.rooms.index') }}" class="mbui-anchor">Live rooms</a>
                <span aria-hidden="true">/</span>
                <span>Room</span>
            </nav>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="mbui-title break-words">{{ $room->title }}</h1>
                <x-learning.room-status :status="$room->status" />
            </div>
            <p class="mt-1 text-sm text-gray-500">
                {{ $start ? $start->format('l, d M Y · H:i').'–'.$room->endsAt()?->setTimezone($tz)->format('H:i') : 'No date set yet' }}
                · hosted by {{ $room->host->name ?? 'nobody' }}
            </p>
        </div>
        <div class="flex gap-2">
            @unless ($room->isDraft())
                <x-mbui.btn-link :href="route('learn.rooms.show', $room)" variant="secondary">Learner view</x-mbui.btn-link>
            @endunless
        </div>
    </div>

    @if ($pageErrors->isNotEmpty())
        <x-mbui.alert type="error" class="mt-4" role="alert">
            <ul class="list-disc space-y-0.5 pl-5">
                @foreach ($pageErrors as $pageError)
                    <li>{{ $pageError }}</li>
                @endforeach
            </ul>
        </x-mbui.alert>
    @endif

    @if ($room->isCancelled())
        <x-mbui.alert type="warning" class="mt-4">
            <p class="font-medium">This room was cancelled.</p>
            @if ($room->cancel_reason)<p class="mt-0.5">Reason: {{ $room->cancel_reason }}</p>@endif
        </x-mbui.alert>
    @endif

    @unless ($canManage)
        <x-mbui.alert type="info" class="mt-4">You can view this room and its attendance, but only its host or a room manager can change it.</x-mbui.alert>
    @endunless

    {{-- Provider health (managers) --}}
    @if ($provider && ($provider['demo'] || ! $provider['configured'] || count($provider['issues'])))
        <x-mbui.alert :type="$provider['configured'] ? 'warning' : 'error'" class="mt-4">
            <p class="font-medium">
                {{ $provider['configured'] ? 'Live video: '.$provider['label'] : 'The live video provider is not configured correctly' }}
                @if ($provider['demo']) <span class="font-normal">(demo mode)</span>@endif
            </p>
            @if (count($provider['issues']))
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($provider['issues'] as $issue)
                        <li>{{ $issue }}</li>
                    @endforeach
                </ul>
            @endif
        </x-mbui.alert>
    @endif

    <div class="mt-6">
        @include('studio.rooms.partials.actions')
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Live now --}}
            @if ($room->isLive())
                <x-mbui.card>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="mbui-section-label">Live now</h2>
                        <a href="{{ route('studio.rooms.participants', $room) }}" class="mbui-anchor text-sm">Manage participants</a>
                    </div>
                    <p class="mt-2 text-sm text-gray-700">
                        Started {{ $room->started_at?->copy()->setTimezone($tz)->format('H:i') }}
                        ({{ $room->started_at?->diffForHumans() }}) · <span class="font-medium">{{ $present->count() }} present now</span>
                    </p>
                    @if ($present->isNotEmpty())
                        <ul class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($present->take(30) as $attendance)
                                <li class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                    {{ $attendance->user->name ?? 'Member' }}@if ($attendance->role === 'host') <span class="text-gray-400">(host)</span>@endif
                                </li>
                            @endforeach
                            @if ($present->count() > 30)
                                <li class="px-2 py-1 text-xs text-gray-500">+{{ $present->count() - 30 }} more</li>
                            @endif
                        </ul>
                    @endif

                    @if ($canManage)
                        <form method="POST" action="{{ route('studio.rooms.announce', $room) }}" class="mt-5 border-t border-gray-100 pt-4"
                            x-data="{ body: @js(old('body', '')) }">
                            @csrf
                            <label for="announce-body" class="mbui-label">Announcement</label>
                            <p class="text-xs text-gray-500">Pinned in the room’s chat and sent as a notification to everyone who can join.</p>
                            <textarea id="announce-body" name="body" x-model="body" rows="2" maxlength="1000" required
                                class="mbui-input mt-2 w-full" placeholder="e.g. We start in 2 minutes — grab your notes!"></textarea>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span class="text-xs text-gray-400"><span x-text="body.length"></span>/1000</span>
                                <x-mbui.button type="submit" x-bind:disabled="!body.trim()" class="disabled:cursor-not-allowed disabled:opacity-50">Post announcement</x-mbui.button>
                            </div>
                            @error('body')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </form>
                    @endif
                </x-mbui.card>
            @endif

            {{-- Overview --}}
            <x-mbui.card>
                <h2 class="mbui-section-label">Overview</h2>
                <dl class="mt-3 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd class="mt-0.5"><x-learning.room-status :status="$room->status" /></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Schedule</dt>
                        <dd class="mt-0.5 text-gray-900">
                            @if ($start)
                                {{ $start->format('D, d M Y · H:i') }} · {{ (int) $room->duration_minutes }} min
                            @else
                                <span class="text-gray-400">Not scheduled</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Who can join</dt>
                        <dd class="mt-0.5 text-gray-900">
                            {{ $room->accessLabel() }}
                            @if ($room->access === 'private')
                                · <a href="{{ route('studio.rooms.participants', $room) }}" class="mbui-anchor">{{ (int) $room->members_count }} invited</a>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Host</dt>
                        <dd class="mt-0.5 text-gray-900">{{ $room->host->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Category / course</dt>
                        <dd class="mt-0.5 text-gray-900">
                            {{ $room->category->name ?? 'No category' }}
                            @if ($room->course) · {{ $room->course->title }}@if ($room->course->trashed()) <span class="text-red-600">(in trash)</span>@endif @endif
                            @if ($room->topic) · {{ $room->topic->title }}@endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">In the room</dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            <x-mbui.badge :appearance="$room->chat_enabled ? 'success' : 'neutral'">Chat {{ $room->chat_enabled ? 'on' : 'off' }}</x-mbui.badge>
                            <x-mbui.badge :appearance="$room->questions_enabled ? 'success' : 'neutral'">Questions {{ $room->questions_enabled ? 'on' : 'off' }}</x-mbui.badge>
                            <x-mbui.badge :appearance="$room->allow_participant_media ? 'success' : 'neutral'">Learner mic/camera {{ $room->allow_participant_media ? 'on' : 'off' }}</x-mbui.badge>
                        </dd>
                    </div>
                </dl>
                @if ($room->description)
                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <h3 class="text-sm font-medium text-gray-500">Description</h3>
                        <div class="research-prose mt-1 break-words text-sm text-gray-700">
                            {!! \Illuminate\Support\Str::markdown($room->description, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                        </div>
                    </div>
                @endif
            </x-mbui.card>

            @include('studio.rooms.partials.sessions')

            @include('studio.rooms.partials.recordings')
        </div>

        <div class="min-w-0 space-y-6">
            <x-mbui.card>
                <h2 class="mbui-section-label">People & attendance</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li>
                        <a href="{{ route('studio.rooms.participants', $room) }}" class="mbui-anchor">Participants</a>
                        <span class="block text-xs text-gray-500">{{ $room->access === 'private' ? 'Invited members and people in the call.' : 'People in the call right now.' }}</span>
                    </li>
                    <li>
                        <a href="{{ route('studio.rooms.attendance', $room) }}" class="mbui-anchor">Attendance report</a>
                        <span class="block text-xs text-gray-500">Time in the call per person, per session — with CSV export.</span>
                    </li>
                </ul>
            </x-mbui.card>

            <x-mbui.card>
                <h2 class="mbui-section-label">Details</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Created</dt><dd class="text-gray-900">{{ $room->created_at?->copy()->setTimezone($tz)->format('d M Y') }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Last started</dt><dd class="text-gray-900">{{ $room->started_at?->copy()->setTimezone($tz)->format('d M Y H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Last ended</dt><dd class="text-gray-900">{{ $room->ended_at?->copy()->setTimezone($tz)->format('d M Y H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Sessions</dt><dd class="text-gray-900">{{ number_format($sessionsTotal) }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Reminder</dt><dd class="text-gray-900">{{ $room->reminder_sent_at ? 'Sent' : ($room->isScheduled() ? '15 min before' : '—') }}</dd></div>
                </dl>
            </x-mbui.card>
        </div>
    </div>
</x-layouts.app>
