@php
    $sections = [
        'agreement' => 'Agreement',
        'services' => 'Our services',
        'account' => 'Your account',
        'payments' => 'Orders, prices and payments',
        'ai-tools' => 'AI tool subscriptions',
        'software' => 'Software and research tools',
        'learning' => 'Courses and live classes',
        'research' => 'Research library and writers',
        'conduct' => 'Acceptable use',
        'ip' => 'Content and intellectual property',
        'refunds' => 'Refunds',
        'scholarships' => 'Scholarships',
        'ending' => 'Suspension and closing your account',
        'third-parties' => 'Third-party services',
        'liability' => 'Disclaimers and liability',
        'law' => 'Governing law',
        'changes' => 'Changes to these terms',
    ];
    $n = array_flip(array_keys($sections));
@endphp

<x-legal.page
    title="Terms of Service"
    updated="7 October 2026"
    intro="The rules for using mbuniehub.com and the MHub apps: your account, payments, AI tools, courses, live classes and research."
    readingTime="8"
    :sections="$sections">

    <x-legal.summary :items="[
        ['M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
            'One account, one person', 'Keep your login private. Shared or resold access can be suspended.'],
        ['M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'Access after payment', 'Orders start once payment is confirmed. Prices are in TZS.'],
        ['M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342',
            'Learn respectfully', 'Be kind in classes and chats. Course content is for your own learning.'],
        ['M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
            'Refunds when we cannot deliver', 'Activated access is generally final; if we cannot deliver, we refund.'],
    ]" />

    <x-legal.section id="agreement" :number="$n['agreement'] + 1" title="Agreement">
        <p>These Terms are an agreement between you and <strong>MbunieEduHub</strong>, Dar es Salaam, Tanzania. They apply when you use mbuniehub.com or the MHub apps. By creating an account, placing an order or joining a class, you accept these Terms and our <a href="{{ route('public.privacy') }}" class="mbui-anchor">Privacy Policy</a>. If you do not agree, please do not use the service.</p>
    </x-legal.section>

    <x-legal.section id="services" :number="$n['services'] + 1" title="Our services">
        <ul>
            <li><strong>AI tools and software</strong> – authorised, managed access to third-party AI tools and software, sold as subscriptions or one-time licences.</li>
            <li><strong>Research tools</strong> – research software delivered with a product key and download after payment.</li>
            <li><strong>Learning</strong> – video courses and lessons, live online classes and class recordings, run by MbunieEduHub and approved instructors.</li>
            <li><strong>Research library</strong> – research papers written by approved writers and reviewed before publication.</li>
            <li><strong>Scholarships</strong> – listings of opportunities offered by other institutions.</li>
        </ul>
        <p>We may add, change or stop features from time to time. If we stop something you have paid for and not yet received, we will offer a replacement or a refund for the unused part.</p>
    </x-legal.section>

    <x-legal.section id="account" :number="$n['account'] + 1" title="Your account">
        <ul>
            <li>Give accurate information and keep it up to date. You must verify your email address.</li>
            <li>An account is for <strong>one person</strong>. Keep your password private; you are responsible for what happens in your account.</li>
            <li>You must be 13 or older. If you are under 18, use MbunieEduHub with the permission of a parent, guardian or your school.</li>
            <li>Tell us straight away if you think someone else has used your account.</li>
        </ul>
    </x-legal.section>

    <x-legal.section id="payments" :number="$n['payments'] + 1" title="Orders, prices and payments">
        <p>Prices are set in <strong>Tanzanian Shillings (TZS)</strong>. USD and CNY amounts are shown for reference only and may differ slightly from what your bank or wallet charges.</p>
        <p>You can pay with mobile money or card through <strong>AzamPay</strong> or <strong>ClickPesa</strong>, with <strong>PayPal</strong>, or by a manual transfer with proof of payment. An order stays <em>pending</em> until the payment is confirmed; automatic payments are usually confirmed within minutes, manual payments after our team checks them. We may refuse a payment that cannot be verified.</p>
        <p>You will receive a receipt by email for every confirmed payment. Payment providers may charge their own fees.</p>
    </x-legal.section>

    <x-legal.section id="ai-tools" :number="$n['ai-tools'] + 1" title="AI tool subscriptions">
        <ul>
            <li>Access to a subscribed AI tool is provided through an account we manage, for the length of the plan you choose. We remind you before it ends.</li>
            <li>Use the tool only for yourself. Sharing your access, changing its login details or reselling it is not allowed and may lead to suspension without refund.</li>
            <li>You must also follow the tool provider's own rules. The provider may change its features, limits or prices; we will tell you if this affects your plan.</li>
        </ul>
    </x-legal.section>

    <x-legal.section id="software" :number="$n['software'] + 1" title="Software and research tools">
        <p>Product keys and downloads are delivered on your order page once payment is confirmed. They are licensed for your own use under the software maker's licence; you may not share, publish or resell them. Download links are personal and expire after a short time; you can open a new one from your order.</p>
    </x-legal.section>

    <x-legal.section id="learning" :number="$n['learning'] + 1" title="Courses and live classes">
        <ul>
            <li>Courses open to all members can be watched by any signed-in member; enrolled courses are for the learners enrolled in them.</li>
            <li>Live classes start and end at the host's discretion. Hosts decide who may speak, use a camera or share a screen, and may remove a participant who disturbs the class.</li>
            <li>Hosts may record a class. Do not record, photograph or share a class or its participants yourself without the permission of the host and the people shown.</li>
        </ul>
    </x-legal.section>

    <x-legal.section id="research" :number="$n['research'] + 1" title="Research library and writers">
        <p>Writers keep the copyright in their research. By submitting research you confirm it is your own original work (or that you have the right to publish it) and you give MbunieEduHub permission to review, publish, display and distribute it on our website and apps.</p>
        <p>We review every submission and may ask for changes, reject it, or unpublish it later — for example for plagiarism, false information or a complaint from a rights holder. Readers may use published research for study with proper citation, but may not republish it without the writer's permission.</p>
    </x-legal.section>

    <x-legal.section id="conduct" :number="$n['conduct'] + 1" title="Acceptable use">
        <p>When you use MbunieEduHub, you must not:</p>
        <ul>
            <li>harass, insult, threaten or discriminate against others in chats, comments or classes;</li>
            <li>post illegal, sexual, violent or hateful content, spam, or content that belongs to someone else;</li>
            <li>try to bypass payments, break into accounts or systems, or disrupt the service;</li>
            <li>copy, download or redistribute paid courses, recordings or research in bulk;</li>
            <li>use the service for anything unlawful under Tanzanian law or the law where you live.</li>
        </ul>
        <p>We may remove content that breaks these rules.</p>
    </x-legal.section>

    <x-legal.section id="ip" :number="$n['ip'] + 1" title="Content and intellectual property">
        <p>The MbunieEduHub name, logo, website, apps and our own course material belong to MbunieEduHub. Instructors and writers own their own material. You get a personal, non-transferable right to use the content you have access to for your own learning.</p>
        <p>You keep ownership of what you upload (messages, files, comments). You allow us to store and show it as needed to run the service — for example to show your comment to other learners.</p>
    </x-legal.section>

    <x-legal.section id="refunds" :number="$n['refunds'] + 1" title="Refunds">
        <ul>
            <li>Because access and product keys are activated for you individually, payments are <strong>generally non-refundable once access is delivered</strong>.</li>
            <li>If we cannot activate or deliver your order, we will refund it in full.</li>
            <li>Double or mistaken payments for the same order are refunded once we confirm them.</li>
            <li>Ask for a refund through the support chat or the contact page, with your order number.</li>
        </ul>
        <p>This does not limit any rights you have under consumer protection law.</p>
    </x-legal.section>

    <x-legal.section id="scholarships" :number="$n['scholarships'] + 1" title="Scholarships">
        <p>Scholarship listings are for information only. We are not the awarding institution and cannot guarantee eligibility, results or that an opportunity is still open. Always confirm the details with the institution before you apply, and never pay anyone who promises a scholarship in our name.</p>
    </x-legal.section>

    <x-legal.section id="ending" :number="$n['ending'] + 1" title="Suspension and closing your account">
        <p>We may suspend or close an account that breaks these Terms, misuses payments or puts other users at risk. Where reasonable, we will tell you why and give you a chance to respond.</p>
        <p>You can close your account at any time: in the app go to <strong>Profile → Delete my account</strong>, or see our <a href="{{ route('public.account-deletion') }}" class="mbui-anchor">account deletion page</a>. Active subscriptions end when the account is deleted.</p>
    </x-legal.section>

    <x-legal.section id="third-parties" :number="$n['third-parties'] + 1" title="Third-party services">
        <p>Some parts of the service are provided by others — AI tool and software makers, payment providers and scholarship institutions. Their own terms also apply, and we are not responsible for their services, outages or changes beyond our reasonable control.</p>
    </x-legal.section>

    <x-legal.section id="liability" :number="$n['liability'] + 1" title="Disclaimers and liability">
        <p>We work hard to keep MbunieEduHub available and accurate, but the service is provided "as is" and may sometimes be interrupted. To the extent the law allows, we are not liable for indirect or consequential losses (such as lost profits or data), and our total liability for any claim is limited to the amount you paid us for the service concerned.</p>
        <p>Nothing in these Terms excludes liability that cannot be excluded by law.</p>
    </x-legal.section>

    <x-legal.section id="law" :number="$n['law'] + 1" title="Governing law">
        <p>These Terms are governed by the laws of the United Republic of Tanzania. We will always try to solve a problem with you first; if we cannot, disputes will be handled by the courts of Tanzania.</p>
    </x-legal.section>

    <x-legal.section id="changes" :number="$n['changes'] + 1" title="Changes to these terms">
        <p>We may update these Terms when our services change. The date at the top shows the latest version. For important changes we will let you know in the app or by email before they take effect; using the service after that means you accept the new Terms.</p>
    </x-legal.section>

</x-legal.page>
