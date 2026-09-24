<x-layouts.app title="Video lessons" header="Teaching Studio">

    {{-- The admin layout has no [x-cloak] rule; keep hidden Alpine parts hidden until Alpine starts. --}}
    <style>[x-cloak]{display:none !important;}</style>
    @php
        $canCreate = auth()->user()->can('create', \App\Models\LearningVideo::class);
        $completion = function ($video) {
            $viewers = (int) $video->viewers_count;
            return $viewers > 0 ? min(100, (int) round(((int) $video->completions_count / $viewers) * 100)) : null;
        };
    @endphp

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Video lessons</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ $seesAll ? 'Every recorded lesson on the platform.' : 'The lessons you teach.' }}
                Upload, publish and keep them up to date.
            </p>
        </div>
        @if ($canCreate)
            <div class="flex gap-2">
                <x-mbui.btn-link :href="route('studio.videos.create')">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Add video
                </x-mbui.btn-link>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="mt-6 flex flex-wrap gap-1 border-b border-gray-200 text-sm" role="tablist" aria-label="Lesson status">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('studio.videos.index', array_filter(['tab' => $key === 'all' ? null : $key, 'q' => $search ?: null, 'category' => $categoryId, 'course' => $courseId])) }}"
                role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                class="border-b-2 px-3 py-2 font-medium {{ $tab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
                <span class="ml-1 rounded-full px-1.5 text-xs {{ $tab === $key ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">{{ number_format($counts[$key]) }}</span>
            </a>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('studio.videos.index') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
        @if ($tab !== 'all')
            <input type="hidden" name="tab" value="{{ $tab }}">
        @endif
        <div class="sm:col-span-2 lg:col-span-1">
            <label for="filter-q" class="sr-only">Search lessons</label>
            <input id="filter-q" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search by title…" class="mbui-input w-full">
        </div>
        <div>
            <label for="filter-category" class="sr-only">Category</label>
            <select id="filter-category" name="category" class="mbui-input w-full">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-course" class="sr-only">Course</label>
            <select id="filter-course" name="course" class="mbui-input w-full">
                <option value="">All courses</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected($courseId === $course->id)>{{ $course->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
            <x-mbui.button type="submit" variant="secondary" class="flex-1 lg:flex-none">Filter</x-mbui.button>
            @if ($filtered)
                <x-mbui.btn-link :href="route('studio.videos.index', $tab !== 'all' ? ['tab' => $tab] : [])" variant="ghost" class="flex-1 lg:flex-none">Clear</x-mbui.btn-link>
            @endif
        </div>
    </form>

    @if ($videos->isEmpty())
        <div class="mt-4">
            @if ($counts['all'] === 0 && ! $filtered)
                <x-learning.empty title="No video lessons yet"
                    :message="$canCreate ? 'Upload your first lesson — large files are uploaded in pieces, so a slow connection is fine.' : 'Lessons appear here once instructors upload them.'"
                    :action-href="$canCreate ? route('studio.videos.create') : null" :action-label="$canCreate ? 'Add video' : null" />
            @else
                <x-learning.empty title="No lessons match"
                    :message="$filtered ? 'Try another search term or clear the filters.' : 'There are no lessons in this tab.'"
                    :action-href="$filtered ? route('studio.videos.index', $tab !== 'all' ? ['tab' => $tab] : []) : null"
                    :action-label="$filtered ? 'Clear filters' : null" />
            @endif
        </div>
    @else
        {{-- Desktop table --}}
        <div class="mbui-card mt-4 hidden overflow-hidden md:block">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="mbui-th">Video</th>
                        <th scope="col" class="mbui-th hidden lg:table-cell">Category</th>
                        <th scope="col" class="mbui-th">Course</th>
                        <th scope="col" class="mbui-th">Status</th>
                        <th scope="col" class="mbui-th text-right">Views</th>
                        <th scope="col" class="mbui-th">Completion</th>
                        <th scope="col" class="mbui-th hidden xl:table-cell">Created</th>
                        <th scope="col" class="mbui-th text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($videos as $video)
                        @php($rate = $completion($video))
                        <tr class="hover:bg-gray-50">
                            <td class="mbui-td">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="relative aspect-video w-24 shrink-0 overflow-hidden rounded-md bg-gray-100">
                                        <x-learning.thumb :src="$video->thumbnailUrl()" alt="" />
                                        @if ($video->duration_seconds)
                                            <span class="absolute bottom-1 right-1 rounded bg-gray-900/80 px-1 text-[10px] font-medium text-white">{{ $video->durationLabel() }}</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        @can('update', $video)
                                            <a href="{{ route('studio.videos.edit', $video) }}" class="line-clamp-2 font-medium text-gray-900 hover:text-indigo-600">{{ $video->title }}</a>
                                        @else
                                            <a href="{{ route('learn.videos.show', $video) }}" class="line-clamp-2 font-medium text-gray-900 hover:text-indigo-600">{{ $video->title }}</a>
                                        @endcan
                                        <p class="mt-0.5 truncate text-xs text-gray-500">
                                            @if ($seesAll){{ $video->instructor->name ?? 'No instructor' }} · @endif
                                            <span class="lg:hidden">{{ $video->category->name ?? '—' }} · </span>{{ $video->durationLabel() }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="mbui-td hidden text-sm text-gray-600 lg:table-cell">{{ $video->category->name ?? '—' }}</td>
                            <td class="mbui-td text-sm text-gray-600">
                                @if ($video->course)
                                    <span class="line-clamp-2">{{ $video->course->title }}</span>
                                    @if ($video->course->trashed())<span class="text-xs text-red-600">(in trash)</span>@endif
                                @else
                                    <span class="text-gray-400">Standalone</span>
                                @endif
                            </td>
                            <td class="mbui-td">
                                <div class="flex flex-col items-start gap-1">
                                    <x-mbui.status-badge :status="$video->status" />
                                    @unless ($video->hasFile())
                                        <x-mbui.badge appearance="warning">No file</x-mbui.badge>
                                    @endunless
                                </div>
                            </td>
                            <td class="mbui-td text-right text-sm tabular-nums text-gray-700">{{ number_format((int) $video->views) }}</td>
                            <td class="mbui-td">
                                @if ($rate === null)
                                    <span class="text-sm text-gray-400">—</span>
                                @else
                                    <div class="w-24">
                                        <x-learning.progress-bar :percent="$rate" />
                                        <p class="mt-1 text-xs text-gray-500">{{ $rate }}% · {{ (int) $video->completions_count }}/{{ (int) $video->viewers_count }}</p>
                                    </div>
                                @endif
                            </td>
                            <td class="mbui-td hidden text-sm text-gray-500 xl:table-cell">{{ $video->created_at?->format('d M Y') }}</td>
                            <td class="mbui-td">
                                @include('studio.videos.partials.row-actions', ['video' => $video, 'impact' => $impacts[$video->id] ?? []])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <ul class="mt-4 space-y-3 md:hidden">
            @foreach ($videos as $video)
                @php($rate = $completion($video))
                <li class="mbui-card overflow-hidden">
                    <div class="flex gap-3 p-4">
                        <div class="relative aspect-video w-28 shrink-0 overflow-hidden rounded-md bg-gray-100">
                            <x-learning.thumb :src="$video->thumbnailUrl()" alt="" />
                            @if ($video->duration_seconds)
                                <span class="absolute bottom-1 right-1 rounded bg-gray-900/80 px-1 text-[10px] font-medium text-white">{{ $video->durationLabel() }}</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            @can('update', $video)
                                <a href="{{ route('studio.videos.edit', $video) }}" class="line-clamp-2 text-sm font-medium text-gray-900">{{ $video->title }}</a>
                            @else
                                <a href="{{ route('learn.videos.show', $video) }}" class="line-clamp-2 text-sm font-medium text-gray-900">{{ $video->title }}</a>
                            @endcan
                            <p class="mt-0.5 truncate text-xs text-gray-500">
                                {{ $video->category->name ?? '—' }}
                                · {{ $video->course ? $video->course->title : 'Standalone' }}
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                <x-mbui.status-badge :status="$video->status" />
                                @unless ($video->hasFile())
                                    <x-mbui.badge appearance="warning">No file</x-mbui.badge>
                                @endunless
                            </div>
                        </div>
                    </div>
                    <dl class="grid grid-cols-3 gap-2 border-t border-gray-100 px-4 py-3 text-xs">
                        <div>
                            <dt class="text-gray-500">Views</dt>
                            <dd class="font-medium tabular-nums text-gray-900">{{ number_format((int) $video->views) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Completion</dt>
                            <dd class="font-medium text-gray-900">{{ $rate === null ? '—' : $rate.'%' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Created</dt>
                            <dd class="font-medium text-gray-900">{{ $video->created_at?->format('d M Y') }}</dd>
                        </div>
                    </dl>
                    <div class="border-t border-gray-100 bg-gray-50 px-4 py-2">
                        @include('studio.videos.partials.row-actions', ['video' => $video, 'impact' => $impacts[$video->id] ?? [], 'mobile' => true])
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $videos->links() }}</div>
    @endif
</x-layouts.app>
