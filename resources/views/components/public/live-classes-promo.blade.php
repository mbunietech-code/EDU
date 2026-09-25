@props(['live' => 0, 'sessions' => []])

{{-- Home-page section about live classes & online meetings. Plain copy and
     only real data (public rooms that are live or scheduled) — no mock-ups. --}}
@php
    $joinUrl = auth()->check() ? route('learn.rooms.index') : route('register');
@endphp

<section id="live-classes" class="scroll-mt-20 border-b border-gray-200 bg-white" aria-labelledby="live-classes-title">
    <div class="mbui-container py-14 sm:py-16">
        <div class="grid gap-10 lg:grid-cols-5 lg:gap-12">
            <div class="lg:col-span-3">
                <p class="text-sm font-semibold text-indigo-600">Live classes</p>
                <h2 id="live-classes-title" class="mt-2 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                    Join live classes and hold online meetings
                </h2>
                <p class="mt-4 max-w-2xl text-base leading-7 text-gray-600">
                    Instructors teach live on MbunieEduHub. You see and hear them, ask questions in the chat,
                    and can watch the recording afterwards. Schools and teams can also use it for their own
                    online meetings and conferences.
                </p>

                <ul class="mt-6 space-y-2 text-sm text-gray-700">
                    <li class="flex gap-2"><span class="text-indigo-600" aria-hidden="true">&ndash;</span> Works in the browser on a phone or computer, nothing to install.</li>
                    <li class="flex gap-2"><span class="text-indigo-600" aria-hidden="true">&ndash;</span> Camera, microphone and screen sharing for the teacher.</li>
                    <li class="flex gap-2"><span class="text-indigo-600" aria-hidden="true">&ndash;</span> Chat and questions during the class.</li>
                    <li class="flex gap-2"><span class="text-indigo-600" aria-hidden="true">&ndash;</span> Private rooms for a class, a team or a mentorship group.</li>
                </ul>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ $joinUrl }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        {{ auth()->check() ? 'See live classes' : 'Create a free account' }}
                    </a>
                    <a href="{{ route('public.contact') }}" class="text-sm font-semibold text-gray-900 hover:text-indigo-600">
                        Book a meeting or conference <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>

            {{-- Real schedule --}}
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">
                            @if ($live > 0)
                                {{ $live }} {{ Str::plural('class', $live) }} live right now
                            @else
                                Upcoming classes
                            @endif
                        </h3>
                        @if (count($sessions))
                            <a href="{{ $joinUrl }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">See all</a>
                        @endif
                    </div>

                    @if (count($sessions))
                        <ul class="divide-y divide-gray-100">
                            @foreach ($sessions as $session)
                                <li>
                                    <a href="{{ $session['url'] }}" class="flex items-start justify-between gap-4 px-5 py-3 hover:bg-gray-50">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-gray-900">{{ $session['title'] }}</span>
                                            <span class="block truncate text-xs text-gray-500">
                                                {{ $session['is_live'] ? 'Happening now' : $session['when'] }}@if ($session['host']) · {{ $session['host'] }}@endif
                                            </span>
                                        </span>
                                        @if ($session['is_live'])
                                            <span class="shrink-0 text-xs font-semibold text-red-600">Live</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="px-5 py-6 text-sm text-gray-600">
                            No class is scheduled right now. New dates are posted here as soon as instructors publish them.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
