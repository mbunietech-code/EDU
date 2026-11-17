<x-layouts.admin title="Research process stages" header="Research">

    <div class="mbui-page-header">
        <div>
            <a href="{{ route('admin.research-requests.index') }}" class="mbui-anchor text-sm">&larr; Research requests</a>
            <h1 class="mbui-title mt-1">Process stages</h1>
            <p class="mt-1 text-sm text-gray-500">The steps every research request goes through. Customers see them as their progress tracker.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <ol class="space-y-3" role="list">
                @foreach ($stages as $i => $stage)
                    <li class="mbui-card p-4" x-data="{ edit: false }">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white" style="background-color: {{ $stage->hex() }}">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 font-semibold text-gray-900">
                                    {{ $stage->name }}
                                    <span class="text-xs font-normal text-gray-500">{{ $stage->progress }}%</span>
                                    @if ($stage->isSystem())<span class="rounded bg-gray-100 px-1.5 text-[10px] font-semibold uppercase text-gray-600" title="The workflow moves requests here by itself">built-in</span>@endif
                                    @if ($stage->allows_cancel)<span class="rounded bg-sky-50 px-1.5 text-[10px] font-semibold uppercase text-sky-700">customer can cancel</span>@endif
                                    @unless ($stage->is_active)<span class="rounded bg-red-50 px-1.5 text-[10px] font-semibold uppercase text-red-700">off</span>@endunless
                                </p>
                                <p class="truncate text-sm text-gray-500">{{ $stage->description ?: '—' }}</p>
                            </div>
                            <div class="hidden shrink-0 text-right text-xs text-gray-500 sm:block">
                                <span class="font-semibold text-gray-900">{{ $stage->active_count }}</span> active<br>{{ $stage->requests_count }} total
                            </div>
                            @if ($canManage)
                                <div class="flex shrink-0 items-center gap-1">
                                    <form method="POST" action="{{ route('admin.research-requests.stages.move', $stage) }}">
                                        @csrf<input type="hidden" name="dir" value="up">
                                        <button type="submit" @disabled($loop->first) class="rounded p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30" aria-label="Move up"><x-research-requests.icon name="up" class="h-4 w-4" /></button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.research-requests.stages.move', $stage) }}">
                                        @csrf<input type="hidden" name="dir" value="down">
                                        <button type="submit" @disabled($loop->last) class="rounded p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30" aria-label="Move down"><x-research-requests.icon name="down" class="h-4 w-4" /></button>
                                    </form>
                                    <button type="button" @click="edit = ! edit" class="rounded px-2 py-1 text-sm font-medium text-indigo-600 hover:bg-indigo-50">Edit</button>
                                </div>
                            @endif
                        </div>

                        @if ($canManage)
                            <div x-show="edit" x-cloak class="mt-4 border-t border-gray-100 pt-4">
                                <form method="POST" action="{{ route('admin.research-requests.stages.update', $stage) }}" class="grid gap-3 sm:grid-cols-2">
                                    @csrf
                                    @method('PUT')
                                    @include('admin.research-requests.partials.stage-fields', ['stage' => $stage])
                                    <div class="flex items-center justify-between gap-2 sm:col-span-2">
                                        @if (! $stage->isSystem())
                                            <button type="submit" form="delete-stage-{{ $stage->id }}" class="text-sm font-medium text-red-600 hover:underline">Delete stage</button>
                                        @else
                                            <span class="text-xs text-gray-400">Built-in stages can be renamed and moved, not deleted.</span>
                                        @endif
                                        <x-mbui.button type="submit">Save</x-mbui.button>
                                    </div>
                                </form>
                                @if (! $stage->isSystem())
                                    <form id="delete-stage-{{ $stage->id }}" method="POST" action="{{ route('admin.research-requests.stages.destroy', $stage) }}" onsubmit="return confirm('Delete this stage?')">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>

        @if ($canManage)
            <aside>
                <section class="mbui-card p-5 lg:sticky lg:top-6">
                    <h2 class="text-sm font-semibold text-gray-900">Add a stage</h2>
                    <form method="POST" action="{{ route('admin.research-requests.stages.store') }}" class="mt-3 grid gap-3">
                        @csrf
                        @include('admin.research-requests.partials.stage-fields', ['stage' => new \App\Models\ResearchRequestStage(['color' => 'indigo', 'progress' => 50, 'is_active' => true])])
                        <div>
                            <label for="after" class="mbui-label">Place after</label>
                            <select id="after" name="after" class="mbui-input mt-1">
                                <option value="">At the end</option>
                                @foreach ($stages as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-mbui.button type="submit">Add stage</x-mbui.button>
                    </form>
                </section>
            </aside>
        @endif
    </div>

</x-layouts.admin>
