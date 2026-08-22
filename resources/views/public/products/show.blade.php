@if (auth()->check() && ! auth()->user()->is_admin)
    <x-layouts.user :title="$product->name" :header="$product->name">
        @include('public.products._show-content')
    </x-layouts.user>
@else
    <x-layouts.public :title="$product->name" :meta-description="$product->meta_description ?? $product->description">
        @include('public.products._show-content')
    </x-layouts.public>
@endif
