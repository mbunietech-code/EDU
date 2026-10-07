@php
    $sections = [
        'in-the-app' => 'In the app',
        'without-the-app' => 'Without the app',
        'what-happens' => 'What is deleted, and when',
    ];
@endphp

<x-legal.page
    title="Delete your account"
    updated="7 October 2026"
    intro="How to delete your MbunieEduHub account and personal data. This applies to the website and the MHub apps for Android and Windows."
    :sections="$sections">

    <x-legal.section id="in-the-app" number="1" title="In the app">
        <ol class="list-decimal space-y-1.5 pl-5">
            <li>Open the MHub app and sign in.</li>
            <li>Go to <strong>Profile</strong> → <strong>Delete my account</strong>.</li>
            <li>Enter your password and confirm.</li>
        </ol>
        <p>You are signed out straight away and we confirm by email when the deletion is complete.</p>
    </x-legal.section>

    <x-legal.section id="without-the-app" number="2" title="Without the app">
        <p>Send us a request from the <a href="{{ route('public.contact') }}" class="mbui-anchor">contact page</a> with the subject <strong>"Account deletion request"</strong>, using the email address of your account. We may ask you to confirm the request from that email address.</p>
    </x-legal.section>

    <x-legal.section id="what-happens" number="3" title="What is deleted, and when">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-red-100 bg-red-50 p-4">
                <p class="text-sm font-semibold text-red-800">Deleted within 30 days</p>
                <p class="mt-1 text-sm text-red-700">Your account, name, email, phone number, profile, support messages, learning progress, comments and uploaded files.</p>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                <p class="text-sm font-semibold text-amber-800">Kept for up to 7 years</p>
                <p class="mt-1 text-sm text-amber-700">Orders, payment references and receipts, without your profile, because tax and accounting law requires it. Then deleted.</p>
            </div>
        </div>
        <p>Active subscriptions end when the account is deleted. More details are in our <a href="{{ route('public.privacy') }}" class="mbui-anchor">Privacy Policy</a>.</p>
    </x-legal.section>

</x-legal.page>
