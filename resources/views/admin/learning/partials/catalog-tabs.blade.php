@php
    $catalogTabs = [
        'courses' => ['Courses', route('admin.learning.courses.index')],
        'categories' => ['Categories', route('admin.learning.categories.index')],
        'topics' => ['Topics', route('admin.learning.topics.index')],
    ];
@endphp
<nav class="mt-6 flex gap-1 overflow-x-auto border-b border-gray-200 text-sm" aria-label="Catalogue sections">
    @foreach ($catalogTabs as $key => [$label, $href])
        <a href="{{ $href }}"
            @if ($active === $key) aria-current="page" @endif
            class="whitespace-nowrap border-b-2 px-3 py-2 font-medium {{ $active === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
