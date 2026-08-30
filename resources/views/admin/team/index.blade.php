<x-layouts.admin title="Team" header="Team">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Team</h1>
            <p class="mt-1 text-sm text-gray-500">Admins and what each of them can access.</p>
        </div>
        <a href="{{ route('admin.team.create') }}">
            <x-mbui.button>Add admin</x-mbui.button>
        </a>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Name</th>
                    <th class="mbui-th">Email</th>
                    <th class="mbui-th">Role</th>
                    <th class="mbui-th">Access</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($admins as $admin)
                    <tr>
                        <td class="mbui-td font-medium text-gray-900">
                            {{ $admin->name }}
                            @if ($admin->id === auth()->id())
                                <span class="text-xs text-gray-400">(you)</span>
                            @endif
                        </td>
                        <td class="mbui-td text-gray-600">{{ $admin->email }}</td>
                        <td class="mbui-td">
                            @if ($admin->role === 'super_admin')
                                <span class="inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800">Super admin</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">Admin</span>
                            @endif
                        </td>
                        <td class="mbui-td text-sm text-gray-500">
                            @if ($admin->role === 'super_admin')
                                Everything
                            @elseif ($admin->permissions === null)
                                <span class="text-amber-600">Full (not yet restricted)</span>
                            @else
                                {{ count($admin->permissions) }} permission(s)
                            @endif
                        </td>
                        <td class="mbui-td">
                            @if ($admin->id === auth()->id())
                                <span class="text-sm text-gray-400">—</span>
                            @else
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.team.edit', $admin) }}" class="mbui-anchor text-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.team.destroy', $admin) }}"
                                          onsubmit="return confirm('Remove admin access for {{ $admin->name }}? They become a normal member.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Revoke</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">No admins yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.admin>
