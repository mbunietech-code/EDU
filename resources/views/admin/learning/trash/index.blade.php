<x-layouts.admin title="Learning trash" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Trash</h1>
            <p class="mt-1 text-sm text-gray-500">Deleted learning content is kept for {{ $retentionDays }} days, then removed for good. Restore anything deleted by mistake.</p>
        </div>
    </div>

    <nav class="mt-6 flex gap-1 overflow-x-auto border-b border-gray-200 text-sm" aria-label="Trash sections">
        @foreach ($types as $key => [$label])
            <a href="{{ route('admin.learning.trash.index', ['type' => $key]) }}"
                @if ($type === $key) aria-current="page" @endif
                class="whitespace-nowrap border-b-2 px-3 py-2 font-medium {{ $type === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ number_format($counts[$key] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    @if ($items->isEmpty())
        <x-learning.empty class="mt-4" :title="'No deleted '.strtolower($types[$type][0])" message="Items you delete will wait here until they are restored or purged." />
    @else
        <x-mbui.card class="mt-4 overflow-hidden p-0">
            <div class="hidden grid-cols-12 gap-4 border-b border-gray-200 bg-gray-50 px-6 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 md:grid">
                <div class="col-span-5">Item</div>
                <div class="col-span-2">Deleted</div>
                <div class="col-span-2">Removed for good</div>
                <div class="col-span-3"><span class="sr-only">Actions</span></div>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($items as $item)
                    @php
                        $label = $item->title ?? $item->name ?? ('#'.$item->id);
                        $purgeAt = $item->deleted_at->copy()->addDays($retentionDays);
                        $details = match ($type) {
                            'course' => array_filter([$item->category?->name, $item->videos_count.' '.Str::plural('lesson', $item->videos_count)]),
                            'video' => array_filter([$item->course?->title, $item->category?->name]),
                            'room' => array_filter([$item->host?->name ? 'Host: '.$item->host->name : null, $item->sessions_count.' '.Str::plural('session', $item->sessions_count)]),
                            default => [$item->courses_count.' '.Str::plural('course', $item->courses_count)],
                        };
                        $impact = match ($type) {
                            'course' => ['Its topics, enrolments and learner progress are removed', 'Lessons still in the course lose their course link'],
                            'video' => ['The video file, quality versions, resources, comments and progress are removed'],
                            'room' => ['Its sessions, attendance, chat and recordings are removed'],
                            default => ['The category is removed for good'],
                        };
                    @endphp
                    <li class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-4 md:grid-cols-12 md:items-center md:px-6">
                        <div class="col-span-2 min-w-0 md:col-span-5">
                            <p class="truncate font-medium text-gray-900">{{ $label }}</p>
                            @if ($details)
                                <p class="truncate text-xs text-gray-500">{{ implode(' · ', $details) }}</p>
                            @endif
                        </div>
                        <div class="text-sm text-gray-700 md:col-span-2">
                            <span class="block text-xs text-gray-500 md:hidden">Deleted</span>
                            <time datetime="{{ $item->deleted_at->toIso8601String() }}" title="{{ $item->deleted_at->format('d M Y H:i') }}">{{ $item->deleted_at->diffForHumans() }}</time>
                        </div>
                        <div class="text-sm text-gray-700 md:col-span-2">
                            <span class="block text-xs text-gray-500 md:hidden">Removed for good</span>
                            {{ $purgeAt->isPast() ? 'Next cleanup' : $purgeAt->format('d M Y') }}
                        </div>
                        <div class="col-span-2 flex flex-wrap items-center justify-end gap-2 md:col-span-3">
                            <form method="POST" action="{{ route('admin.learning.trash.restore', ['type' => $type, 'id' => $item->id]) }}">
                                @csrf
                                <x-mbui.button type="submit" variant="secondary" :aria-label="'Restore '.$label">Restore</x-mbui.button>
                            </form>
                            <x-learning.confirm-delete :action="route('admin.learning.trash.destroy', ['type' => $type, 'id' => $item->id])"
                                :title="'Delete “'.$label.'” forever?'" :impact="[...$impact, 'This cannot be undone']" button-label="Delete forever">
                                <x-slot:trigger>
                                    <x-mbui.button variant="ghost" class="text-red-700" :aria-label="'Delete '.$label.' forever'">Delete forever</x-mbui.button>
                                </x-slot:trigger>
                            </x-learning.confirm-delete>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-mbui.card>
        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</x-layouts.admin>
