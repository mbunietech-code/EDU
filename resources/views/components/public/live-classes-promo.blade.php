@props(['live' => 0, 'sessions' => []])

{{-- Home-page promo for live classes & online meetings. Guests are sent to
     register / log in; the session list only ever holds public rooms. --}}
@php
    $features = [
        ['Video meetings', 'Face-to-face classes and meetings in the browser — nothing to install.', 'M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z'],
        ['Screen sharing', 'Present slides, code or documents to everyone in the room.', 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25'],
        ['Chat and Q&A', 'Ask questions live and let the host answer them one by one.', 'M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155'],
        ['Recordings', 'Missed the session? Watch the recording later at your own pace.', 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z'],
        ['Attendance', 'Hosts see who joined and for how long, and can export a report.', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['Private rooms', 'Invite-only meetings for a team, a class or a mentorship group.', 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z'],
    ];
    $tiles = [
        ['AJ', 'Amina', 'bg-indigo-500'],
        ['BM', 'Baraka', 'bg-emerald-600'],
        ['NM', 'Neema', 'bg-amber-500'],
        ['JK', 'Juma', 'bg-sky-600'],
        ['SM', 'Salma', 'bg-rose-500'],
    ];
    $joinUrl = auth()->check() ? route('learn.rooms.index') : route('register');
@endphp

<section id="live-classes" class="scroll-mt-20 bg-indigo-950" aria-labelledby="live-classes-title">
    <div class="mbui-container py-16 sm:py-20">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            {{-- Pitch --}}
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-200 ring-1 ring-inset ring-white/15">
                    <span class="relative flex h-2 w-2" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75 motion-reduce:hidden"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                    </span>
                    New · Live classes & meetings
                </span>

                <h2 id="live-classes-title" class="mt-5 text-3xl font-bold tracking-tight text-white sm:text-4xl">
                    Learn live. Meet online.<br>
                    <span class="text-indigo-300">All in one place.</span>
                </h2>
                <p class="mt-4 max-w-xl text-lg text-indigo-100/80">
                    Join live classes with real instructors, hold online meetings and conferences for your team, and rewatch every lesson whenever you like — straight from your browser or phone.
                </p>

                <ul class="mt-8 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    @foreach ($features as [$title, $text, $icon])
                        <li class="flex gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-indigo-200" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                                </svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-white">{{ $title }}</span>
                                <span class="block text-sm text-indigo-100/70">{{ $text }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ $joinUrl }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-indigo-950 shadow-sm hover:bg-indigo-50">
                        {{ auth()->check() ? 'Browse live classes' : 'Join a live class — free account' }}
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a href="{{ route('public.contact') }}" class="inline-flex items-center justify-center rounded-lg bg-white/10 px-6 py-3 text-sm font-semibold text-white ring-1 ring-inset ring-white/20 hover:bg-white/20">
                        Book a meeting or conference
                    </a>
                </div>
            </div>

            {{-- Meeting preview + real sessions --}}
            <div class="space-y-4">
                <div class="overflow-hidden rounded-2xl bg-gray-900 shadow-2xl ring-1 ring-white/10" aria-hidden="true">
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Live</span>
                            <span class="text-sm font-medium text-white">Weekly live class</span>
                        </div>
                        <span class="hidden text-xs text-gray-400 sm:inline">24 participants</span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 p-3">
                        <div class="relative col-span-2 row-span-2 flex items-center justify-center rounded-lg bg-indigo-900">
                            <div class="text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-500 text-base font-bold text-white sm:h-16 sm:w-16 sm:text-xl">DI</div>
                                <p class="mt-2 text-[11px] font-medium text-indigo-100 sm:text-xs">Instructor · sharing screen</p>
                            </div>
                            <span class="absolute bottom-2 left-2 rounded bg-black/50 px-1.5 py-0.5 text-[10px] text-white">Instructor</span>
                        </div>
                        @foreach ($tiles as [$initials, $name, $color])
                            <div class="relative flex aspect-video items-center justify-center rounded-lg bg-gray-800">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full {{ $color }} text-[10px] font-semibold text-white sm:h-9 sm:w-9 sm:text-xs">{{ $initials }}</span>
                                <span class="absolute bottom-1 left-1 rounded bg-black/50 px-1 text-[10px] text-white">{{ $name }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-center gap-2 border-t border-white/10 px-4 py-3">
                        @foreach (['M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z',
                                  'M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z',
                                  'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25',
                                  'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z'] as $path)
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" /></svg>
                            </span>
                        @endforeach
                        <span class="ml-2 rounded-full bg-red-600 px-4 py-2 text-xs font-semibold text-white">Leave</span>
                    </div>
                </div>

                @if (count($sessions))
                    <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-white">
                                @if ($live > 0)
                                    {{ $live }} {{ Str::plural('class', $live) }} live right now
                                @else
                                    Upcoming live classes
                                @endif
                            </h3>
                            <a href="{{ $joinUrl }}" class="text-xs font-semibold text-indigo-300 hover:text-white">See all &rarr;</a>
                        </div>
                        <ul class="mt-3 divide-y divide-white/10">
                            @foreach ($sessions as $session)
                                <li>
                                    <a href="{{ $session['url'] }}" class="flex items-center gap-3 py-2.5 hover:opacity-90">
                                        @if ($session['is_live'])
                                            <span class="shrink-0 rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">Live</span>
                                        @else
                                            <span class="shrink-0 rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-indigo-200">Soon</span>
                                        @endif
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-white">{{ $session['title'] }}</span>
                                            <span class="block truncate text-xs text-indigo-100/60">
                                                {{ $session['is_live'] ? 'Happening now' : $session['when'] }}@if ($session['host']) · {{ $session['host'] }}@endif
                                            </span>
                                        </span>
                                        <span class="shrink-0 text-xs font-semibold text-indigo-300">{{ $session['is_live'] ? 'Join' : 'Details' }} &rarr;</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
