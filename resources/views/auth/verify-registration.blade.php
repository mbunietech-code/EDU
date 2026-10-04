<x-layouts.public title="Verify Registration">

    <section class="mbui-container flex min-h-[72vh] items-center justify-center py-16">
        <div class="w-full max-w-lg">
            <div class="mbui-card p-6 sm:p-8">
                <div class="mb-5 flex justify-center">
                    <x-brand-mark class="h-12 w-12" rounded="rounded-xl" />
                </div>
                <h1 class="mbui-title text-center">Confirm your email</h1>
                <p class="mt-2 text-center text-sm leading-6 text-gray-500">
                    We sent a 6-digit code to <span class="font-medium text-gray-700">{{ $pending->email }}</span>.
                    Your account will be created after this code is verified.
                </p>

                @if (session('status') === 'verification-code-sent')
                    <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        A fresh verification code has been sent to your email.
                    </div>
                @endif

                <form method="POST" action="{{ route('register.verify.store') }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="code" value="Verification Code" />
                        <x-text-input id="code" class="mbui-input mt-1 text-center text-lg font-semibold tracking-[0.35em]" type="text" inputmode="numeric" name="code" :value="old('code')" required autofocus maxlength="6" autocomplete="one-time-code" placeholder="000000" />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Create verified account
                    </button>
                </form>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <form method="POST" action="{{ route('register.verify.resend') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                            Send a new code
                        </button>
                    </form>

                    <a href="{{ route('register') }}" class="text-sm font-medium text-gray-500 hover:text-gray-800">
                        Change details
                    </a>
                </div>
            </div>
        </div>
    </section>

</x-layouts.public>
