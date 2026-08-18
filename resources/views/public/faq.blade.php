<x-layouts.public title="Frequently Asked Questions" metaDescription="Answers to common questions about MBUNIETECH AI access subscriptions, payments and support.">

    <section class="mbui-container py-16">
        <div class="max-w-3xl">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Frequently Asked Questions</h1>
            <p class="mt-3 text-gray-600">Everything you need to know about MBUNIETECH.</p>
        </div>

        <div class="mt-10 max-w-3xl space-y-4" x-data="{ open: null }">
            @foreach ([
                ['How do I subscribe?', 'Browse the AI tools catalogue, choose a product and plan, create an order, then follow the payment instructions. After we verify your payment, your subscription is activated automatically.'],
                ['What payment methods do you accept?', 'We support bank transfer and mobile money. After creating your order you will see the payment instructions for that order.'],
                ['How quickly is my payment verified?', 'Payments are reviewed manually by our team. Approvals are usually completed within a few hours during business time.'],
                ['What happens when my subscription expires?', 'You will receive a warning before expiry. When your subscription expires, your access is revoked and the account is released. You can renew anytime.'],
                ['Can I share my account credentials?', 'No. Accounts are assigned per user subscription. Sharing credentials is not permitted and may lead to suspension.'],
                ['How do I get support?', 'Use the contact page to reach us. We respond during Tanzanian business hours.'],
            ] as [$q, $a])
                <div class="mbui-card overflow-hidden">
                    <button type="button" class="flex w-full items-center justify-between px-6 py-4 text-left" @click="open = open === {{ $loop->index }} ? null : {{ $loop->index }}">
                        <span class="text-sm font-semibold text-gray-900">{{ $q }}</span>
                        <svg class="h-5 w-5 text-gray-400 transition" :class="open === {{ $loop->index }} ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div x-show="open === {{ $loop->index }}" x-collapse>
                        <p class="px-6 pb-4 text-sm text-gray-600">{{ $a }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

</x-layouts.public>