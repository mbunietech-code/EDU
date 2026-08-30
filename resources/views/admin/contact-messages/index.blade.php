<x-layouts.admin title="Contact Messages" header="Contact Messages">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Contact Messages</h1>
            <p class="mt-1 text-sm text-gray-500">Messages submitted through the public contact form.</p>
        </div>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">From</th>
                    <th class="mbui-th">Subject</th>
                    <th class="mbui-th">Received</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($messages as $message)
                    <tr>
                        <td class="mbui-td">
                            <p class="font-medium {{ $message->is_read ? 'text-gray-900' : 'text-gray-900 font-semibold' }}">{{ $message->name }}</p>
                            <p class="text-xs text-gray-500">{{ $message->email }}</p>
                        </td>
                        <td class="mbui-td">
                            <a href="{{ route('admin.contact-messages.show', $message) }}" class="mbui-anchor">{{ $message->subject }}</a>
                        </td>
                        <td class="mbui-td text-gray-500">{{ $message->created_at->format('d M Y H:i') }}</td>
                        <td class="mbui-td">
                            @if ($message->is_read)
                                <x-mbui.badge appearance="neutral">Read</x-mbui.badge>
                            @else
                                <x-mbui.badge appearance="info">New</x-mbui.badge>
                            @endif
                        </td>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.contact-messages.show', $message) }}" class="mbui-anchor text-sm">View</a>
                                <x-mbui.reasoned-action :action="route('admin.contact-messages.destroy', $message)" method="DELETE" label="Delete" prompt-text="Why are you deleting this message?" class="text-sm font-medium text-red-600 hover:text-red-800" />
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
