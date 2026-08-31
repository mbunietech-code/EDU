<x-layouts.user title="Profile" header="Profile">

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
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" class="mbui-input mt-1" type="email" name="email" :value="old('email', auth()->user()->email)" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
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

        <div class="mbui-card p-6 lg:col-span-2">
            <h2 class="mbui-section-label">Update password</h2>
            <form method="POST" action="{{ route('user.profile.password') }}" class="mt-4 grid gap-4 sm:grid-cols-3">
                @csrf
                @method('PATCH')
                <div>
                    <x-input-label for="current_password" value="Current password" />
                    <x-text-input id="current_password" class="mbui-input mt-1" type="password" name="current_password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password" value="New password" />
                    <x-text-input id="password" class="mbui-input mt-1" type="password" name="password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Confirm new password" />
                    <x-text-input id="password_confirmation" class="mbui-input mt-1" type="password" name="password_confirmation" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>
                <div class="sm:col-span-3">
                    <x-mbui.button type="submit" variant="secondary">Update password</x-mbui.button>
                </div>
            </form>
        </div>
    </div>

</x-layouts.user>