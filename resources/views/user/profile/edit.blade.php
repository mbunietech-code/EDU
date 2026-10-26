{{-- Admins see it inside the admin panel, everyone else in their account area. --}}
<x-dynamic-component :component="auth()->user()->is_admin ? 'layouts.admin' : 'layouts.user'" title="Profile" header="Profile">

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="mbui-card p-6">
            <h2 class="mbui-section-label">Profile information</h2>
            <form method="POST" action="{{ route('user.profile.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" class="mbui-input mt-1" type="text" name="name" :value="old('name', auth()->user()->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ auth()->user()->email }}
                        <a href="{{ route('user.settings') }}" class="ml-2 text-xs font-medium text-indigo-600 hover:text-indigo-500">Change in Settings</a></p>
                </div>
                <x-mbui.button type="submit" variant="secondary">Save changes</x-mbui.button>
            </form>
        </div>

        <div class="mbui-card p-6">
            <h2 class="mbui-section-label">Account status</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Status</dt>
                    <dd><x-mbui.status-badge :status="auth()->user()->status" /></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Member since</dt>
                    <dd class="font-medium text-gray-900">{{ \App\Support\Dates::human(auth()->user()->created_at) }}</dd>
                </div>
            </dl>
        </div>

    </div>

</x-dynamic-component>