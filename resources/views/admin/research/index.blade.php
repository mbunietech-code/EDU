<x-layouts.admin title="Research" header="Research library">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Research library</h1>
            <p class="mt-1 text-sm text-gray-500">Review submissions, publish approved work, and manage categories.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('research.contributor.create')">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New research
            </x-mbui.btn-link>
            @can('research.manage')
                <x-mbui.btn-link :href="route('admin.research.categories')" variant="secondary">Categories</x-mbui.btn-link>
            @endcan
        </div>
    </div>


    <div class="mt-6 flex flex-wrap gap-1 border-b border-gray-200 text-sm">
        @php($tabs = ['review' => 'In review', 'published' => 'Published', 'drafts' => 'Drafts', 'archived' => 'Archived', 'all' => 'All'])
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.research.index', ['status' => $key]) }}"
               class="border-b-2 px-3 py-2 font-medium {{ $tab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
                @if ($key === 'review' && $reviewCount > 0)
                    <span class="ml-1 rounded-full bg-red-600 px-1.5 text-xs text-white">{{ $reviewCount }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <x-mbui.card class="mt-4 p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="mbui-th">Title</th>
                    <th class="mbui-th">Author</th>
                    <th class="mbui-th">Area</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Updated</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($researches as $research)
                    <tr class="hover:bg-gray-50">
                        <td class="mbui-td">
                            <a href="{{ route('admin.research.show', $research) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $research->title }}</a>
                            <span class="block text-xs text-gray-400">{{ $research->chapters_count }} {{ Str::plural('chapter', $research->chapters_count) }}</span>
                        </td>
                        <td class="mbui-td">{{ $research->author->name ?? '—' }}</td>
                        <td class="mbui-td">{{ $research->category->name ?? '—' }}</td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$research->status" /></td>
                        <td class="mbui-td text-gray-400">{{ $research->updated_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-mbui.card>

    <div class="mt-4">{{ $researches->links() }}</div>
</x-layouts.admin>
