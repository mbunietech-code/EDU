<x-layouts.admin title="Users" header="Users">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Users</h1>
        </div>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or email..."
                    class="mbui-input sm:w-72">
                <select name="status" class="mbui-input sm:w-40">
                    <option value="">All statuses</option>
                    @foreach (['active', 'inactive', 'suspended'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-mbui.button type="submit" variant="secondary" class="!px-4 !py-2">Filter</x-mbui.button>
            </form>
        </div>

        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Name</th>
                    <th class="mbui-th">Email</th>
                    <th class="mbui-th">Role</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Joined</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($users as $user)
                    <tr>
                        <td class="mbui-td font-medium text-gray-900">{{ $user->name }}</td>
                        <td class="mbui-td">{{ $user->email }}</td>
                        <td class="mbui-td">
                            @if ($user->is_admin)
                                <x-mbui.badge appearance="info">Admin</x-mbui.badge>
                            @else
                                <x-mbui.badge appearance="neutral">User</x-mbui.badge>
                            @endif
                        </td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$user->status" /></td>
                        <td class="mbui-td text-gray-500">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.users.show', $user) }}" class="mbui-anchor text-sm">View history</a>
                                @if (! $user->is_admin)
                                    @if ($user->status === 'suspended')
                                        <form method="POST" action="{{ route('admin.users.activate', $user) }}">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-emerald-600 hover:text-emerald-800">Activate</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Suspend</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user permanently? This also removes their orders, payments and subscriptions.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-400">No users found</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $users->links() }}</div>

</x-layouts.admin>