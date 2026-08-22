@if (auth()->check() && ! auth()->user()->is_admin)
    <x-layouts.user title="AI Tools" header="AI Tools">
        @include('public.products._index-content')
    </x-layouts.user>
@else
    <x-layouts.public title="AI Tools" metaDescription="Browse our catalogue of authorized AI tools with flexible subscription plans.">
        @include('public.products._index-content')
    </x-layouts.public>
@endif
