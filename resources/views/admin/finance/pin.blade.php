<x-layouts.admin title="Finance" header="Finance">

    <div class="flex min-h-[60vh] items-center justify-center">
        <div class="w-full max-w-sm">
            <div class="mbui-card p-8 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </div>
                <h1 class="mt-4 text-lg font-semibold text-gray-900">Finance Access</h1>
                <p class="mt-1 text-sm text-gray-500">Enter the 6-digit PIN to continue.</p>

                <form method="POST" action="{{ route('admin.finance.pin.verify') }}" class="mt-6"
                    x-data="{ digits: ['', '', '', '', '', ''] }">
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
            </div>
        </div>
    </div>

</x-layouts.admin>
