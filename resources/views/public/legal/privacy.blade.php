@php
    $sections = [
        'who-we-are' => 'Who we are',
        'what-we-collect' => 'Information we collect',
        'how-we-use' => 'How we use it',
        'payments' => 'Payments',
        'live-classes' => 'Live classes and recordings',
        'sharing' => 'Who we share it with',
        'retention' => 'How long we keep it',
        'security' => 'How we protect it',
        'your-rights' => 'Your rights',
        'delete' => 'Deleting your account',
        'children' => 'Children',
        'cookies' => 'Cookies and app permissions',
        'transfers' => 'Data outside Tanzania',
        'changes' => 'Changes to this policy',
    ];
    $n = array_flip(array_keys($sections));
@endphp

<x-legal.page
    title="Privacy Policy"
    updated="7 October 2026"
    intro="How MbunieEduHub collects, uses and protects your personal information on mbuniehub.com and in the MHub apps for Android and Windows."
    readingTime="7"
    :sections="$sections">

    <x-legal.summary :items="[
        ['M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
            'Only what we need', 'We collect what it takes to run your account, your orders and your learning, nothing more.'],
        ['M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636',
            'Never sold, no ads', 'We do not sell your data and there is no advertising on MbunieEduHub.'],
        ['M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
            'Safe payments', 'AzamPay, ClickPesa and PayPal handle payments. We never see your card details or PIN.'],
        ['m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0',
            'You are in control', 'Edit your details any time, and delete your account from the app or the website.'],
    ]" />

    <x-legal.section id="who-we-are" :number="$n['who-we-are'] + 1" title="Who we are">
        <p>MbunieEduHub ("MbunieEduHub", "we", "us") runs the website <strong>mbuniehub.com</strong> and the <strong>MHub</strong> apps. We are based in Dar es Salaam, Tanzania, and we are responsible for the personal information described in this policy.</p>
        <p>This policy applies to everyone who visits the website or uses the apps: members, instructors, research writers, guests in live classes and our own staff accounts.</p>
    </x-legal.section>

    <x-legal.section id="what-we-collect" :number="$n['what-we-collect'] + 1" title="Information we collect">
        <p><strong>Account information</strong> – your name, email address, phone number (optional) and password. Passwords are stored only as a secure hash; nobody at MbunieEduHub can read them.</p>
        <p><strong>Orders and payments</strong> – the products, plans, tools and courses you order, amounts, payment method, transaction references, receipts and, if you pay manually, the proof-of-payment image you upload. When you pay by mobile money we receive the phone number used for the payment.</p>
        <p><strong>Learning activity</strong> – courses you enrol in, lesson progress, live classes you attend and for how long, comments, questions, poll answers and drawings on the class whiteboard.</p>
        <p><strong>Messages and files</strong> – what you send to our support team (text, photos, videos and voice notes), messages sent through the contact form, research you write or import, and materials or recordings you upload as an instructor.</p>
        <p><strong>Technical information</strong> – a notification token for your device (so we can send push notifications), the type of device or browser you use, your IP address and basic logs we keep to keep the service secure and to fix errors.</p>
        <p>We do <strong>not</strong> collect your location, contacts, call logs or SMS, and we do not use advertising IDs.</p>
    </x-legal.section>

    <x-legal.section id="how-we-use" :number="$n['how-we-use'] + 1" title="How we use it">
        <ul>
            <li>To create and run your account and sign you in securely.</li>
            <li>To process orders, confirm payments, deliver access, product keys and downloads, and send receipts.</li>
            <li>To provide courses, live classes, recordings, your learning progress and the research library.</li>
            <li>To answer your support messages and send important notices (payment received, subscription ending, class starting).</li>
            <li>To protect the service: prevent fraud and abuse, investigate errors, and keep records required by law.</li>
            <li>To improve MbunieEduHub using overall, non-identifying usage figures.</li>
        </ul>
        <p>We process your information because it is needed to provide the service you asked for, to meet our legal duties (for example tax and accounting records), for our legitimate interest in keeping the service safe, or with your consent where we ask for it. You can withdraw consent at any time.</p>
    </x-legal.section>

    <x-legal.section id="payments" :number="$n['payments'] + 1" title="Payments">
        <p>Online payments are processed by <strong>AzamPay</strong>, <strong>ClickPesa</strong> and <strong>PayPal</strong>. You enter your card details, mobile-money PIN or PayPal login on their secure pages or on your phone; MbunieEduHub never receives or stores them. Each provider handles your information under its own privacy policy.</p>
        <p>We keep the payment reference, amount, status and receipt so that we can confirm your order and meet accounting rules.</p>
    </x-legal.section>

    <x-legal.section id="live-classes" :number="$n['live-classes'] + 1" title="Live classes and recordings">
        <p>Live classes run on our own video server. Your camera and microphone are only used when you turn them on inside a class, and only when the host allows it.</p>
        <p>The host of a class may record it. On the website a red recording mark is shown to everyone in the class while recording is on. Recordings are available to the host and room staff, and to the class members if the host shares them. Messages, questions and poll answers in a class are visible to the other participants.</p>
        <p>Guests who join through a guest link give only a display name; it is used for that class only.</p>
    </x-legal.section>

    <x-legal.section id="sharing" :number="$n['sharing'] + 1" title="Who we share it with">
        <p>We do <strong>not</strong> sell or rent your personal information. We only share it with:</p>
        <ul>
            <li><strong>Service providers</strong> who work for us: payment providers (AzamPay, ClickPesa, PayPal), our web hosting and video servers, our email and SMS delivery services, and Google Firebase Cloud Messaging for push notifications. They may use the information only to provide their service to us.</li>
            <li><strong>Tool providers</strong>, where we must register an account on your behalf to give you access to a subscribed AI tool or software.</li>
            <li><strong>Authorities</strong>, when the law requires it, or to protect the rights and safety of our users or of MbunieEduHub.</li>
        </ul>
        <p>Your name may be shown to other participants in a live class, and the name of a research author is shown on their published research.</p>
    </x-legal.section>

    <x-legal.section id="retention" :number="$n['retention'] + 1" title="How long we keep it">
        <ul>
            <li><strong>Account, learning and message data</strong> – while your account is active. When you delete your account we delete it within 30 days.</li>
            <li><strong>Orders, payments and receipts</strong> – up to 7 years, because tax and accounting law requires it. After your account is deleted these records are kept without your profile.</li>
            <li><strong>Security and error logs</strong> – for a limited period, then deleted.</li>
            <li><strong>Class recordings</strong> – until the host or our team deletes them.</li>
        </ul>
    </x-legal.section>

    <x-legal.section id="security" :number="$n['security'] + 1" title="How we protect it">
        <ul>
            <li>All traffic between your device and our servers is encrypted (HTTPS).</li>
            <li>Passwords are hashed, and credentials we manage for you are stored encrypted.</li>
            <li>Staff only see the information they need for their role, and administrative actions are logged.</li>
            <li>Uploaded files and recordings are stored privately and are only served to people who are allowed to see them.</li>
        </ul>
        <p>No system is perfectly secure. If a breach affects your information, we will inform you and the authorities as the law requires.</p>
    </x-legal.section>

    <x-legal.section id="your-rights" :number="$n['your-rights'] + 1" title="Your rights">
        <p>Under the Tanzania Personal Data Protection Act, 2022 and similar laws, you can:</p>
        <ul>
            <li>ask for a copy of the personal information we hold about you;</li>
            <li>correct information that is wrong (most of it you can edit yourself in your profile);</li>
            <li>ask us to delete your information or your account;</li>
            <li>object to, or ask us to limit, how we use your information;</li>
            <li>withdraw consent you gave earlier.</li>
        </ul>
        <p>To use these rights, contact us. If you are not satisfied with our answer, you may complain to the Personal Data Protection Commission of Tanzania.</p>
    </x-legal.section>

    <x-legal.section id="delete" :number="$n['delete'] + 1" title="Deleting your account">
        <p>In the MHub app go to <strong>Profile → Delete my account</strong>, or follow the steps on our <a href="{{ route('public.account-deletion') }}" class="mbui-anchor">account deletion page</a>. We delete your account and personal data within 30 days, except the order and payment records described above.</p>
    </x-legal.section>

    <x-legal.section id="children" :number="$n['children'] + 1" title="Children">
        <p>MbunieEduHub is meant for adults and for students aged 13 and over. Students under 18 should use it with the permission of a parent, guardian or their school. We do not knowingly collect information from children under 13; if you believe a child has given us information, contact us and we will delete it.</p>
    </x-legal.section>

    <x-legal.section id="cookies" :number="$n['cookies'] + 1" title="Cookies and app permissions">
        <p>The website uses only the cookies it needs to work: keeping you signed in, protecting forms against misuse and remembering simple settings. We do not use advertising or tracking cookies.</p>
        <p>The apps ask for permission before using your camera and microphone (live classes), photos and files (attachments and uploads) and notifications. You can change these permissions at any time in your phone or computer settings.</p>
    </x-legal.section>

    <x-legal.section id="transfers" :number="$n['transfers'] + 1" title="Data outside Tanzania">
        <p>Some of our service providers (for example hosting, email, PayPal and Google) process data in other countries. When this happens we use providers that protect personal information to a standard comparable to Tanzanian law.</p>
    </x-legal.section>

    <x-legal.section id="changes" :number="$n['changes'] + 1" title="Changes to this policy">
        <p>We may update this policy when our services change. We will change the date at the top of this page, and tell you in the app or by email before an important change takes effect.</p>
    </x-legal.section>

</x-legal.page>
