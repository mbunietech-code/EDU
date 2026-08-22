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
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($tools as $tool)
                    <a href="{{ route('user.tools.show', $tool) }}" class="mbui-card group p-6 transition hover:shadow-md">
                        @if ($tool->imageUrl())
                            <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->name }}" class="h-28 w-full rounded-lg border border-gray-100 bg-gray-50 object-contain p-3">
                        @else
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                </svg>
                            </div>
                        @endif
                        <h3 class="mt-4 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">{{ $tool->name }}</h3>
                        <p class="mt-2 line-clamp-2 text-sm text-gray-500">{{ $tool->description }}</p>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-lg font-bold text-gray-900">TZS {{ number_format($tool->price) }}</span>
                            <span class="mbui-anchor text-sm">View &rarr;</span>
                        </div>
                        <x-currency-conversion :amount="$tool->price" class="mt-1 text-xs font-semibold text-gray-600" />
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $tools->links() }}</div>
        @endif
    </div>

</x-layouts.user>
