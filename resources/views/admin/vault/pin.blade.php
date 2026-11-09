<x-layouts.admin title="Password vault" header="Password vault">

    <div class="flex min-h-[60vh] items-center justify-center">
        <div class="w-full max-w-sm">
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <div class="mbui-card p-8 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                    </svg>
                </div>

                @if ($hasPin)
                    <h1 class="mt-4 text-lg font-semibold text-gray-900">Password vault</h1>
                    <p class="mt-1 text-sm text-gray-500">Enter the 6-digit vault PIN. It locks again after a few idle minutes.</p>

                    <form method="POST" action="{{ route('admin.vault.pin.verify') }}" class="mt-6" x-data="{ digits: ['', '', '', '', '', ''] }">
                        @csrf
                        <div class="flex justify-center gap-2">
                            @for ($i = 0; $i < 6; $i++)
                                <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off"
                                    x-model="digits[{{ $i }}]" x-ref="d{{ $i }}"
                                    @if ($i === 0) autofocus @endif
                                    @input="digits[{{ $i }}] = $event.target.value.replace(/[^0-9]/g, ''); if (digits[{{ $i }}]) { $refs.d{{ $i + 1 }}?.focus() }"
                                    @keydown.backspace="if (!digits[{{ $i }}]) { $refs.d{{ $i - 1 }}?.focus() }"
                                    class="h-12 w-10 rounded-lg border border-gray-300 text-center text-lg font-semibold focus:border-indigo-500 focus:ring-indigo-500">
                            @endfor
                        </div>
                        <input type="hidden" name="pin" :value="digits.join('')">
                        <x-input-error :messages="$errors->get('pin')" class="mt-3" />
                        <x-mbui.button type="submit" class="mt-6 w-full">Unlock</x-mbui.button>
                        <a href="{{ route('admin.dashboard') }}" class="mt-3 block text-sm font-medium text-gray-500 hover:text-gray-700">Cancel</a>
                    </form>
                @elseif ($canSetPin)
                    <h1 class="mt-4 text-lg font-semibold text-gray-900">Set the vault PIN</h1>
                    <p class="mt-1 text-sm text-gray-500">Choose a 6-digit PIN for the vault. Use a different one from the Finance PIN, and share it only with people who need the passwords.</p>
                    <form method="POST" action="{{ route('admin.vault.pin.set') }}" class="mt-6 space-y-3 text-left">
                        @csrf
                        <div>
                            <x-input-label for="new_pin" value="New PIN (6 digits)" />
                            <x-text-input id="new_pin" name="new_pin" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password" class="mbui-input mt-1 w-full" required />
                            <x-input-error :messages="$errors->get('new_pin')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="new_pin_confirmation" value="Repeat the PIN" />
                            <x-text-input id="new_pin_confirmation" name="new_pin_confirmation" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password" class="mbui-input mt-1 w-full" required />
                        </div>
                        <x-mbui.button type="submit" class="w-full">Save PIN</x-mbui.button>
                    </form>
                @else
                    <h1 class="mt-4 text-lg font-semibold text-gray-900">Vault not set up yet</h1>
                    <p class="mt-1 text-sm text-gray-500">A super admin must set the vault PIN first.</p>
                    <a href="{{ route('admin.dashboard') }}" class="mt-6 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">Back to the dashboard</a>
                @endif
            </div>
        </div>
    </div>

</x-layouts.admin>
