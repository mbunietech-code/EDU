{{-- Quick search → learn.search. Vars: $q = '', $type = null (kept as hidden field), $id = 'learn-search' --}}
@php($fieldId = $id ?? 'learn-search')
<form method="GET" action="{{ route('learn.search') }}" role="search" class="w-full">
    @if (! empty($type) && $type !== 'all')
        <input type="hidden" name="type" value="{{ $type }}">
    @endif
    <label for="{{ $fieldId }}" class="sr-only">Search lessons, courses, live classes and instructors</label>
    <div class="flex gap-2">
        <div class="relative min-w-0 flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input id="{{ $fieldId }}" type="search" name="q" value="{{ $q ?? '' }}" maxlength="100"
                placeholder="Search lessons, courses, live classes…" class="mbui-input w-full pl-9" autocomplete="off">
        </div>
        <x-mbui.button type="submit">Search</x-mbui.button>
    </div>
</form>
