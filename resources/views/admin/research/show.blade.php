<x-layouts.admin :title="$research->title" header="Research review">

    <nav class="text-sm text-gray-500">
        <a href="{{ route('admin.research.index') }}" class="hover:text-gray-700">Research</a>
        <span class="mx-1.5">/</span><span class="text-gray-700">{{ Str::limit($research->title, 50) }}</span>
    </nav>

    @if (session('success')) <x-mbui.alert class="mt-4">{{ session('success') }}</x-mbui.alert> @endif

    <div class="mt-4 grid gap-6 lg:grid-cols-3">
        {{-- Content preview --}}
        <div class="lg:col-span-2">
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold text-gray-900">{{ $research->title }}</h1>
                <x-mbui.status-badge :status="$research->status" />
            </div>
            <p class="mt-1 text-sm text-gray-500">
                by {{ $research->author->name ?? '—' }} ({{ $research->author->email ?? '' }}) ·
                {{ $research->category->name ?? 'Uncategorised' }} ·
                submitted {{ $research->submitted_at?->diffForHumans() ?? '—' }}
            </p>
            @if ($research->summary)
                <p class="mt-3 text-sm leading-relaxed text-gray-700">{{ $research->summary }}</p>
            @endif

            @foreach ($research->chapters as $chapter)
                <x-mbui.card class="mt-4 p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold text-gray-900">{{ $chapter->title }}</h2>
                        <a href="{{ route('admin.research.read', [$research, $chapter]) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Open full chapter &rarr;</a>
                    </div>
                    @foreach ($chapter->sections as $section)
                        <div class="mt-4 border-t border-gray-100 pt-4 first:border-0 first:pt-2">
                            <h3 class="text-sm font-semibold text-gray-900">{{ $section->heading }}</h3>
                            <div class="research-prose mt-2 line-clamp-6 text-sm">{!! $section->bodyHtml() !!}</div>
                        </div>
                    @endforeach
                    @if ($chapter->sections->isEmpty())
                        <p class="mt-3 text-xs text-gray-400">No sections.</p>
                    @endif
                </x-mbui.card>
            @endforeach
        </div>

        {{-- Review actions --}}
        <div class="space-y-4">
            @can('research.manage')
                @if (in_array($research->status, ['submitted', 'under_review', 'changes_requested']))
                    <x-mbui.card class="p-5" x-data="{ action: null }">
                        <h2 class="mbui-section-label">Review</h2>

                        <div class="mt-3 space-y-2">
                            <button @click="action = action === 'approve' ? null : 'approve'" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Approve &amp; publish</button>
                            <button @click="action = action === 'changes' ? null : 'changes'" class="w-full rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-400">Request changes</button>
                            <button @click="action = action === 'reject' ? null : 'reject'" class="w-full rounded-lg bg-white px-4 py-2 text-sm font-semibold text-red-600 ring-1 ring-inset ring-red-300 hover:bg-red-50">Reject</button>
                        </div>

                        <form x-show="action === 'approve'" x-cloak method="POST" action="{{ route('admin.research.approve', $research) }}" class="mt-4">
                            @csrf
                            <textarea name="comment" rows="2" class="mbui-input text-sm" placeholder="Optional note to the author"></textarea>
                            <button class="mt-2 w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Confirm approve &amp; publish</button>
                        </form>

                        <form x-show="action === 'changes'" x-cloak method="POST" action="{{ route('admin.research.request-changes', $research) }}" class="mt-4">
                            @csrf
                            <textarea name="comment" rows="3" required class="mbui-input text-sm" placeholder="What needs to change? (required)"></textarea>
                            <button class="mt-2 w-full rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white">Send back to author</button>
                        </form>

                        <form x-show="action === 'reject'" x-cloak method="POST" action="{{ route('admin.research.reject', $research) }}" class="mt-4"
                              onsubmit="return confirm('Reject this research?')">
                            @csrf
                            <textarea name="comment" rows="2" class="mbui-input text-sm" placeholder="Optional reason"></textarea>
                            <button class="mt-2 w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Confirm reject</button>
                        </form>
                    </x-mbui.card>
                @elseif ($research->status === 'published')
                    <x-mbui.card class="p-5">
                        <h2 class="mbui-section-label">Published</h2>
                        <p class="mt-2 text-xs text-gray-500">Live since {{ $research->published_at?->format('d M Y') }}.</p>
                        <form method="POST" action="{{ route('admin.research.unpublish', $research) }}" class="mt-3">
                            @csrf
                            <button class="text-xs font-medium text-red-600 hover:text-red-800">Unpublish</button>
                        </form>
                    </x-mbui.card>
                @endif

                <form method="POST" action="{{ route('admin.research.destroy', $research) }}"
                      onsubmit="const r = prompt('Delete this research. Reason?'); if (!r) return false; this.reason.value = r; return true;">
                    @csrf @method('DELETE')
                    <input type="hidden" name="reason">
                    <button class="text-xs font-medium text-red-600 hover:text-red-800">Delete research</button>
                </form>
            @endcan

            @if ($research->reviews->isNotEmpty())
                <x-mbui.card class="p-5">
                    <h2 class="mbui-section-label">History</h2>
                    <ol class="mt-3 space-y-3">
                        @foreach ($research->reviews as $review)
                            <li class="text-xs">
                                <p class="font-semibold text-gray-800">{{ $review->actionLabel() }}</p>
                                <p class="text-gray-400">{{ $review->reviewer->name ?? 'System' }} · {{ $review->created_at->diffForHumans() }}</p>
                                @if ($review->comment)<p class="mt-1 text-gray-600">{{ $review->comment }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </x-mbui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
