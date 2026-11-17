@props(['tracker' => [], 'rr'])

{{-- Stage progress tracker: % complete, the current stage and every step of the process. --}}
@php
    $cancelled = $rr->status === \App\Models\ResearchRequest::CANCELLED;
    $completed = $rr->status === \App\Models\ResearchRequest::COMPLETED;
    $barColor = $cancelled ? '#9CA3AF' : ($completed ? '#059669' : ($rr->stage?->hex() ?? '#4F46E5'));
@endphp

<div {{ $attributes->merge(['class' => 'mbui-card overflow-hidden']) }}>
    <div class="flex flex-wrap items-end justify-between gap-3 bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-5 text-white sm:px-6">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-100">Where your work is</p>
            <p class="mt-1 text-xl font-bold">
                @if ($cancelled)
                    Cancelled
                @elseif ($completed)
                    Completed
                @else
                    {{ $rr->stage?->name ?? 'Received' }}
                @endif
            </p>
            @if (! $cancelled && ! $completed && $rr->stage?->description)
                <p class="mt-0.5 text-sm text-indigo-100">{{ $rr->stage->description }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-4xl font-extrabold leading-none tabular-nums">{{ $rr->progress }}<span class="text-xl font-bold">%</span></p>
            <p class="mt-1 text-xs text-indigo-100">complete</p>
        </div>
    </div>

    <div class="px-5 pt-4 sm:px-6">
        <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $rr->progress }}" aria-label="Progress">
            <div class="h-full rounded-full transition-all duration-700" style="width: {{ $rr->progress }}%; background-color: {{ $barColor }}"></div>
        </div>
    </div>

    <ol class="grid gap-x-2 gap-y-4 px-5 py-5 sm:px-6 {{ count($tracker) > 6 ? 'sm:grid-cols-4 lg:grid-cols-8' : 'sm:grid-cols-3 lg:grid-cols-6' }}" role="list">
        @foreach ($tracker as $i => $step)
            @php($isCurrent = $step['state'] === 'current' && ! $cancelled)
            @php($isDone = $step['state'] === 'done')
            <li class="flex items-start gap-3 sm:flex-col sm:items-center sm:gap-2 sm:text-center">
                <span @class([
                    'relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold ring-4',
                    'text-white ring-white' => $isDone || $isCurrent,
                    'bg-gray-100 text-gray-400 ring-white' => ! $isDone && ! $isCurrent,
                ]) @if ($isDone || $isCurrent) style="background-color: {{ $isDone ? '#059669' : $step['color'] }}" @endif>
                    @if ($isDone)
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                    @if ($isCurrent)
                        <span class="absolute inset-0 animate-ping rounded-full opacity-30" style="background-color: {{ $step['color'] }}"></span>
                    @endif
                </span>
                <span class="min-w-0">
                    <span @class(['block text-sm', 'font-bold text-gray-900' => $isCurrent, 'font-medium text-gray-700' => $isDone, 'text-gray-400' => ! $isDone && ! $isCurrent])>{{ $step['name'] }}</span>
                    @if ($isCurrent)
                        <span class="block text-xs font-medium" style="color: {{ $step['color'] }}">Now</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ol>
</div>
