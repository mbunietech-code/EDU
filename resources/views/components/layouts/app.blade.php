@props(['title' => config('app.name', 'MbunieEduHub'), 'header' => null])

{{-- Renders the signed-in area inside the viewer's own chrome: the admin
     console for admins, the member dashboard for everyone else. Keeps
     shared pages (the research library, contributor portal) from bouncing
     a user out to the marketing site. --}}
@if (auth()->user()?->is_admin)
    <x-layouts.admin :title="$title" :header="$header">
        {{ $slot }}
    </x-layouts.admin>
@else
    <x-layouts.user :title="$title" :header="$header">
        {{ $slot }}
    </x-layouts.user>
@endif
