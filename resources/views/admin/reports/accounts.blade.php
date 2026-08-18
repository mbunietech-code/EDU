<x-layouts.admin title="Accounts Report" header="Accounts Report">

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($report as $row)
            <div class="mbui-card p-6">
                <p class="mbui-section-label">{{ ucwords(str_replace('_', ' ', $row['status'])) }}</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">{{ $row['count'] }}</p>
            </div>
        @empty
            <x-mbui.card>
                <x-mbui.empty-state title="No accounts" />
            </x-mbui.card>
        @endforelse
    </div>

</x-layouts.admin>