<x-layouts.public title="Forgot Password">

    <section class="mbui-container flex min-h-[72vh] items-center justify-center py-16">
        <div class="w-full max-w-md">
            <div class="mbui-card p-6 sm:p-8">
                <div class="mb-5 flex justify-center">
                    <x-brand-mark class="h-12 w-12" rounded="rounded-xl" />
                </div>
                <h1 class="mbui-title text-center">Reset your password</h1>
                <p class="mt-2 text-center text-sm leading-6 text-gray-500">
                    Enter your account email and we will send a secure reset link.
                </p>

                @if (session('status'))
                    <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="email" value="Email Address" />
                        <x-text-input id="email" class="mbui-input mt-1" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Send reset link
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-gray-500">
                    Remembered it?
                    <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-800">Back to login</a>
                </p>
            </div>
        </div>
    </section>

</x-layouts.public>
