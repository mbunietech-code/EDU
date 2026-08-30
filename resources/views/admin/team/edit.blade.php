<x-layouts.admin title="Edit admin" header="Team">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $member->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $member->email }}</p>
        </div>
        <a href="{{ route('admin.team.index') }}" class="mbui-anchor text-sm">Back to team</a>
    </div>

    <div class="mt-6 mbui-card p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.team.update', $member) }}" class="space-y-5">
            @csrf
            @method('PUT')

            @php
                $selected = $member->permissions;
                if ($selected === null) {
                    // Not yet restricted — pre-tick everything so saving keeps full access
                    // unless the super admin unticks some.
                    $selected = \App\Support\Permissions::keys();
                }
            @endphp

            @if ($member->permissions === null && $member->role !== 'super_admin')
                <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                    This admin currently has <strong>full access</strong> (never restricted). Untick items to limit them.
                </p>
            @endif

            @include('admin.team._permissions', [
                'selected' => old('permissions', $selected),
                'currentRole' => old('role', $member->role ?: 'admin'),
            ])
            <x-input-error :messages="$errors->get('role')" class="mt-2" />

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.team.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Save permissions</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>
