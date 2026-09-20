<x-layouts.admin title="{{ $group ? 'Group settings' : 'New group' }}" header="Team Chat">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $group ? 'Group settings' : 'New group' }}</h1>
            <p class="mt-1 text-sm text-gray-500">Chagua admins watakaokuwa kwenye group. Wote wanaweza kusoma na kuandika; ni super admin tu anayebadilisha wanachama.</p>
        </div>
        <a href="{{ $group ? route('admin.team-chat.groups.show', $group) : route('admin.team-chat.index') }}" class="mbui-anchor text-sm">&larr; Back</a>
    </div>

    <div class="mt-6 mbui-card max-w-2xl p-6">
        <form method="POST" action="{{ $group ? route('admin.team-chat.groups.update', $group) : route('admin.team-chat.groups.store') }}" class="space-y-5">
            @csrf
            @if ($group) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Group name" />
                <input id="name" name="name" type="text" maxlength="100" required class="mbui-input mt-1" value="{{ old('name', $group?->name) }}" placeholder="e.g. Support team, Finance">
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Members" />
                <div class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200">
                    @foreach ($admins as $admin)
                        <label class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-gray-50">
                            <input type="checkbox" name="members[]" value="{{ $admin->id }}" class="rounded border-gray-300 text-indigo-600"
                                   @checked(in_array($admin->id, old('members', $selected)))>
                            <span class="text-sm text-gray-900">{{ $admin->name }}</span>
                            <span class="text-xs text-gray-400">{{ $admin->email }}</span>
                            @if ($admin->isSuperAdmin())
                                <x-mbui.badge appearance="info" class="ml-auto">Super admin</x-mbui.badge>
                            @endif
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('members')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <x-mbui.button type="submit">{{ $group ? 'Save changes' : 'Create group' }}</x-mbui.button>
            </div>
        </form>

        @if ($group)
            <form method="POST" action="{{ route('admin.team-chat.groups.destroy', $group) }}" class="mt-6 border-t border-gray-100 pt-4"
                  onsubmit="return confirm('Futa group hili na meseji zake zote? Hatua hii haiwezi kurudishwa.');">
                @csrf
                @method('DELETE')
                <x-mbui.button type="submit" variant="danger">Delete group</x-mbui.button>
            </form>
        @endif
    </div>

</x-layouts.admin>
