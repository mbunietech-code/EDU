{{-- Room list filters: search, category, course (the tab is kept). --}}
<form method="GET" action="{{ route('learn.rooms.index') }}" class="mbui-card mt-4 p-4" role="search">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
        <div class="sm:col-span-2">
            <label for="room-q" class="mbui-label">Search</label>
            <input id="room-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100"
                placeholder="Class title or description" class="mbui-input mt-1 w-full">
        </div>
        <div>
            <label for="room-category" class="mbui-label">Category</label>
            <select id="room-category" name="category" class="mbui-input mt-1 w-full">
                <option value="">All categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->slug }}" @selected($activeCategory?->id === $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="room-course" class="mbui-label">Course</label>
            <select id="room-course" name="course" class="mbui-input mt-1 w-full">
                <option value="">All courses</option>
                @foreach ($courses as $c)
                    <option value="{{ $c->slug }}" @selected($activeCourse?->id === $c->id)>{{ $c->title }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-3">
        <x-mbui.button type="submit">Apply filters</x-mbui.button>
        @if ($isFiltered)
            <a href="{{ route('learn.rooms.index', ['tab' => $tab]) }}" class="mbui-anchor text-sm">Reset</a>
        @endif
    </div>
</form>
