<x-layouts.public title="Terms of Service" metaDescription="Terms of Service for MbunieEduHub.">

    <section class="mbui-container py-16">
        <div class="max-w-3xl">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Terms of Service</h1>
            <p class="mt-2 text-sm text-gray-500">Last updated: {{ now()->format('d M Y') }}</p>

            <div class="mt-8 space-y-8 text-sm leading-relaxed text-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">1. Who we are</h2>
                    <p class="mt-2">MbunieEduHub ("we", "us", "our") provides authorized, managed access to third-party AI tools and research software, and lists third-party scholarship opportunities, to individuals and businesses in Tanzania and beyond. By creating an account or using our services, you agree to these Terms.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">2. Accounts</h2>
                    <p class="mt-2">You must provide accurate information when registering and are responsible for keeping your login credentials secure. You are responsible for all activity that happens under your account.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">3. Orders and payments</h2>
                    <p class="mt-2">Prices are shown in Tanzanian Shillings (TZS); USD and CNY figures are approximate conversions for reference only. Payments are submitted manually with proof of payment and are reviewed by our team before access is activated. We reserve the right to reject a payment that cannot be verified.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">4. Access and subscriptions</h2>
                    <p class="mt-2">Access to a subscribed AI tool is provided through an account we manage on your behalf, for the duration of your chosen plan. Product keys for one-time tools are delivered after payment is confirmed. Sharing your access with people outside your account, or using it in a way that violates the underlying tool provider's own terms, may result in suspension without refund.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">5. Refunds</h2>
                    <p class="mt-2">Because access and product keys are activated individually after manual verification, payments are generally non-refundable once access has been granted. If we are unable to activate your order, we will contact you to resolve or refund the payment.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">6. Scholarships</h2>
                    <p class="mt-2">Scholarship listings are provided for informational purposes only. We are not the awarding institution and do not guarantee eligibility, outcome, or continued availability of any listed opportunity. Always confirm details directly with the institution before applying.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">7. Acceptable use</h2>
                    <p class="mt-2">You agree not to use our services for unlawful purposes, to attempt to bypass payment verification, or to resell managed access without our written permission.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">8. Limitation of liability</h2>
                    <p class="mt-2">Our services are provided "as is". To the extent permitted by law, we are not liable for indirect or consequential losses arising from your use of a third-party tool we provide access to.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">9. Changes to these terms</h2>
                    <p class="mt-2">We may update these Terms from time to time. Continued use of our services after a change means you accept the updated Terms.</p>
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">10. Contact</h2>
                    <p class="mt-2">Questions about these Terms can be sent through our <a href="{{ route('public.contact') }}" class="mbui-anchor">contact page</a>.</p>
                </div>
            </div>
        </div>
    </section>

</x-layouts.public>
