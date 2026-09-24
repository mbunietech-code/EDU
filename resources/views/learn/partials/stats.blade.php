{{-- Headline numbers from ProgressService::stats(). Var: $stats --}}
@php($dur = \App\Http\Controllers\Learn\DashboardController::durationText((int) ($stats['watch_seconds'] ?? 0)))
<dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ([
        ['label' => 'Lessons started', 'value' => number_format($stats['started'] ?? 0), 'icon' => 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z'],
        ['label' => 'Lessons completed', 'value' => number_format($stats['completed'] ?? 0), 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Watch time', 'value' => $dur, 'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Courses completed', 'value' => number_format($stats['courses_completed'] ?? 0).' / '.number_format($stats['courses_enrolled'] ?? 0), 'icon' => 'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342'],
    ] as $stat)
        <div class="mbui-card flex items-center gap-3 p-4">
            <div class="rounded-lg bg-indigo-50 p-2 text-indigo-600" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}" />
                </svg>
            </div>
            <div class="min-w-0">
                <dt class="truncate text-xs font-medium text-gray-500">{{ $stat['label'] }}</dt>
                <dd class="text-lg font-bold tracking-tight text-gray-900">{{ $stat['value'] }}</dd>
            </div>
        </div>
    @endforeach
</dl>
