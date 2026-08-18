<x-layouts.public title="Contact" metaDescription="Get in touch with MbunieEduHub for support and inquiries.">

    <section class="mbui-container py-16">
        <div class="grid gap-10 lg:grid-cols-2">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Contact us</h1>
                <p class="mt-3 text-gray-600">Have a question or need help? Send us a message and we'll get back to you during Tanzanian business hours.</p>

                <dl class="mt-8 space-y-4 text-sm">
                    <div class="flex gap-3">
                        <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                        </svg>
                        <dd class="text-gray-600">+255 000 000 000</dd>
                    </div>
                    <div class="flex gap-3">
                        <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                        <dd class="text-gray-600">support@mbunietech.co.tz</dd>
                    </div>
                    <div class="flex gap-3">
                        <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                        <dd class="text-gray-600">Dar es Salaam, Tanzania</dd>
                    </div>
                </dl>
            </div>

            <div class="mbui-card p-6">
                <form method="POST" action="{{ route('public.contact.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" class="mbui-input mt-1" type="text" name="name" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" class="mbui-input mt-1" type="email" name="email" :value="old('email')" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="subject" value="Subject" />
                        <x-text-input id="subject" class="mbui-input mt-1" type="text" name="subject" :value="old('subject')" required />
                        <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="message" value="Message" />
                        <textarea id="message" name="message" rows="5" class="mbui-input mt-1" required>{{ old('message') }}</textarea>
                        <x-input-error :messages="$errors->get('message')" class="mt-2" />
                    </div>
                    <x-mbui.button type="submit" class="w-full">Send message</x-mbui.button>
                </form>
            </div>
        </div>
    </section>

</x-layouts.public>