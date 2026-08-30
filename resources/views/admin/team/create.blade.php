<x-layouts.admin title="Add admin" header="Team">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Add admin</h1>
        </div>
        <a href="{{ route('admin.team.index') }}" class="mbui-anchor text-sm">Back to team</a>
    </div>

    <div class="mt-6 mbui-card p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.team.store') }}" class="space-y-5">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Full name" />
                    <x-text-input id="name" name="name" class="mbui-input mt-1" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mbui-input mt-1" :value="old('email')" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="password" value="Temporary password" />
                    <x-text-input id="password" name="password" type="password" class="mbui-input mt-1" required />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-500">Share it with them; they can change it after signing in.</p>
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Confirm password" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mbui-input mt-1" required />
                </div>
            </div>

            @include('admin.team._permissions', ['selected' => old('permissions', []), 'currentRole' => old('role', 'admin')])
            <x-input-error :messages="$errors->get('role')" class="mt-2" />

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.team.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Create admin</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>
