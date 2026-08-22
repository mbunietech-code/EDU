<x-layouts.user title="Research Tools" header="Research Tools">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Research Tools</h1>
            <p class="mt-1 text-sm text-gray-500">Programs and apps with a product key delivered after payment approval.</p>
        </div>
    </div>

    <div class="mt-8">
        @if ($tools->isEmpty())
            <x-mbui.card>
                <x-mbui.empty-state title="No tools available yet" message="Check back soon for new research tools." />
            </x-mbui.card>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($tools as $tool)
                    <a href="{{ route('user.tools.show', $tool) }}" class="mbui-card group flex items-center gap-3 p-3 transition hover:shadow-md">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-100 bg-gray-50">
                            @if ($tool->imageUrl())
                                <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->name }}" class="h-full w-full object-contain p-1.5">
                            @else
                                <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="truncate text-sm font-semibold text-gray-900 group-hover:text-indigo-600">{{ $tool->name }}</h3>
                                @if ($tool->is_featured)
                                    <x-mbui.badge appearance="warning" class="shrink-0">Featured</x-mbui.badge>
                                @endif
                            </div>
                            <p class="mt-1 text-sm font-bold text-gray-900">TZS {{ number_format($tool->price) }}</p>
                            <x-currency-conversion :amount="$tool->price" class="text-xs font-semibold text-gray-600" />
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $tools->links() }}</div>
        @endif
    </div>

</x-layouts.user>
