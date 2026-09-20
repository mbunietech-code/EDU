<x-layouts.admin title="Contact Messages" header="Contact Messages">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Contact Messages</h1>
            <p class="mt-1 text-sm text-gray-500">Messages submitted through the public contact form.</p>
        </div>
    </div>

    {{-- Long sender names / subjects (common in spam) wrap instead of stretching the
         table, so no horizontal scrollbar. --}}
    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th px-4">From</th>
                    <th class="mbui-th px-4">Subject</th>
                    <th class="mbui-th px-4">Received</th>
                    <th class="mbui-th px-4">Status</th>
                    <th class="mbui-th px-4">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($messages as $message)
                    <tr>
                        <td class="mbui-td w-1/3 max-w-xs whitespace-normal break-words px-4">
                            <p class="font-medium {{ $message->is_read ? 'text-gray-900' : 'text-gray-900 font-semibold' }}">{{ $message->name }}</p>
                            <p class="break-all text-xs text-gray-500">{{ $message->email }}</p>
                        </td>
                        <td class="mbui-td whitespace-normal break-words px-4">
                            <a href="{{ route('admin.contact-messages.show', $message) }}" class="mbui-anchor">{{ $message->subject }}</a>
                        </td>
                        <td class="mbui-td px-4 text-gray-500">{{ $message->created_at->format('d M Y') }}<p class="text-xs text-gray-400">{{ $message->created_at->format('H:i') }}</p></td>
                        <td class="mbui-td px-4">
                            @if ($message->is_read)
                                <x-mbui.badge appearance="neutral">Read</x-mbui.badge>
                            @else
                                <x-mbui.badge appearance="info">New</x-mbui.badge>
                            @endif
                        </td>
                        <td class="mbui-td px-4">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.contact-messages.show', $message) }}" class="mbui-anchor text-sm">View</a>
                                <x-mbui.reasoned-action :action="route('admin.contact-messages.destroy', $message)" method="DELETE" label="Delete" prompt-text="Why are you deleting this message?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">No messages yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $messages->links() }}</div>

</x-layouts.admin>
