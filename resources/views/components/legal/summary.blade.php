{{-- Key points at the top of a legal page: a grid of small cards. Each item: [icon svg path, heading, text]. --}}
@props(['title' => 'The short version', 'items' => []])

<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        @foreach ($items as [$icon, $heading, $text])
            <div class="flex gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ $heading }}</p>
                    <p class="mt-0.5 text-sm leading-6 text-gray-600">{{ $text }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>
