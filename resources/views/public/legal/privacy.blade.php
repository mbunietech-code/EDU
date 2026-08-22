<x-layouts.public title="Privacy Policy" metaDescription="Privacy Policy for MbunieEduHub.">

    <section class="mbui-container py-16">
        <div class="max-w-3xl">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Privacy Policy</h1>
            <p class="mt-2 text-sm text-gray-500">Last updated: {{ now()->format('d M Y') }}</p>

            <div class="mt-8 space-y-8 text-sm leading-relaxed text-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">1. Information we collect</h2>
                    <p class="mt-2">When you create an account or place an order, we collect information such as your name, email address, phone number (where provided), payment method reference, and payment proof images. We also keep a record of your orders, subscriptions, and support messages so we can serve you.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">2. How we use your information</h2>
                    <p class="mt-2">We use your information to create and manage your account, verify payments, activate and manage your access to tools and subscriptions, communicate with you about your orders, and respond to support messages.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">3. Payment information</h2>
                    <p class="mt-2">We do not process card payments directly. You submit payment through your own mobile money or payment provider and upload proof of payment for manual review. We store the proof image and transaction reference you provide in order to verify and audit your payment.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">4. How we protect your information</h2>
                    <p class="mt-2">Sensitive account credentials we manage on your behalf are stored encrypted. Access to customer data within our team is limited to what is needed to provide support and process orders, and administrative actions are logged.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">5. Sharing your information</h2>
                    <p class="mt-2">We do not sell your personal information. We may share limited information with the underlying AI tool or service provider where required to provision your access, and with payment or communication providers strictly to deliver our service.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">6. Data retention</h2>
                    <p class="mt-2">We retain account and order records for as long as your account is active and as needed to meet our legal, accounting, and support obligations.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">7. Your choices</h2>
                    <p class="mt-2">You can update your profile information at any time from your account settings. To request deletion of your account or data, contact us using the details below.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">8. Changes to this policy</h2>
                    <p class="mt-2">We may update this Privacy Policy from time to time. We will update the "Last updated" date above when we do.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">9. Contact us</h2>
                    <p class="mt-2">For privacy questions or requests, please reach us through our <a href="{{ route('public.contact') }}" class="mbui-anchor">contact page</a>.</p>
                </div>
            </div>
        </div>
    </section>

</x-layouts.public>
