<x-layouts.user title="My Research" header="My Research">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">My Research</h1>
            <p class="mt-1 text-sm text-gray-500">Write your research, then submit it for review. Once approved it is published to the library.</p>
        </div>
        <x-mbui.btn-link :href="route('research.contributor.create')">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            New research
        </x-mbui.btn-link>
    </div>


    <div class="mt-6 space-y-3">
        @forelse ($researches as $research)
            <a href="{{ route('research.contributor.edit', $research) }}" class="mbui-card flex items-center justify-between gap-4 p-5 transition hover:shadow-md">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="truncate text-base font-semibold text-gray-900">{{ $research->title }}</h3>
                        <x-mbui.status-badge :status="$research->status" />
                    </div>
                    <p class="mt-1 text-xs text-gray-400">
                        {{ $research->category->name ?? 'Uncategorised' }} ·
                        {{ $research->chapters_count }} {{ Str::plural('chapter', $research->chapters_count) }} ·
                        updated {{ $research->updated_at->diffForHumans() }}
                    </p>
                    @if ($research->status === 'changes_requested' && $research->review_note)
                        <p class="mt-2 rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-800">Changes requested: {{ Str::limit($research->review_note, 120) }}</p>
                    @endif
                </div>
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>
        @empty
            <x-mbui.card>
                <x-mbui.empty-state title="No research yet" message="Create your first research to get started." />
            </x-mbui.card>
        @endforelse
    </div>
</x-layouts.user>
