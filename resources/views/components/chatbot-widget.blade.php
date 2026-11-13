@php
    try {
        $chatbotLive = app(\App\Support\ChatbotSettings::class)->isLive();
    } catch (\Throwable $e) {
        $chatbotLive = false;
    }
@endphp

@if ($chatbotLive)
    {{-- Styled inline on purpose: works even before the next asset build. --}}
    <style>
        .mb-bot { position: fixed; right: 16px; bottom: 16px; z-index: 9998; font-family: inherit; }
        .mb-bot [hidden] { display: none !important; }
        .mb-bot__fab { display: flex; align-items: center; gap: 10px; border: 0; background: none; padding: 0; cursor: pointer; }
        .mb-bot__fab-label { display: none; padding: 8px 12px; border-radius: 12px; background: #fff; color: #1f2937; font-size: 14px; font-weight: 500; box-shadow: 0 10px 25px rgba(0,0,0,.15); border: 1px solid #e5e7eb; }
        .mb-bot__fab-btn { display: flex; align-items: center; justify-content: center; width: 60px; height: 60px; border-radius: 9999px; background: #fff; box-shadow: 0 10px 25px rgba(0,0,0,.25); border: 2px solid rgba(79,70,229,.3); transition: transform .15s; }
        .mb-bot__fab:hover .mb-bot__fab-btn { transform: scale(1.06); }
        .mb-bot__fab-btn img { width: 48px; height: 48px; object-fit: contain; }
        .mb-bot__panel { position: fixed; right: 16px; bottom: 16px; width: min(380px, calc(100vw - 32px)); height: min(560px, calc(100vh - 32px)); display: flex; flex-direction: column; background: #fff; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,.25); border: 1px solid #e5e7eb; overflow: hidden; }
        .mb-bot__head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #4f46e5; color: #fff; }
        .mb-bot__head img { width: 34px; height: 34px; border-radius: 9999px; background: #fff; object-fit: contain; }
        .mb-bot__title { font-size: 15px; font-weight: 600; line-height: 1.2; }
        .mb-bot__sub { font-size: 12px; opacity: .85; }
        .mb-bot__close { margin-left: auto; background: none; border: 0; color: #fff; font-size: 22px; line-height: 1; cursor: pointer; padding: 4px 6px; }
        .mb-bot__body { flex: 1; overflow-y: auto; padding: 14px; background: #f9fafb; display: flex; flex-direction: column; gap: 8px; }
        .mb-bot__msg { max-width: 85%; padding: 9px 12px; border-radius: 14px; font-size: 14px; line-height: 1.45; white-space: pre-line; word-wrap: break-word; overflow-wrap: anywhere; }
        .mb-bot__msg--bot { align-self: flex-start; background: #fff; color: #1f2937; border: 1px solid #e5e7eb; border-bottom-left-radius: 4px; }
        .mb-bot__msg--user { align-self: flex-end; background: #4f46e5; color: #fff; border-bottom-right-radius: 4px; }
        .mb-bot__msg a { color: #4338ca; text-decoration: underline; }
        .mb-bot__msg--user a { color: #fff; }
        .mb-bot__support { align-self: flex-start; display: inline-block; padding: 7px 12px; border-radius: 9999px; background: #eef2ff; color: #4338ca; font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid #c7d2fe; }
        .mb-bot__chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .mb-bot__chip { padding: 6px 10px; border-radius: 9999px; background: #fff; border: 1px solid #c7d2fe; color: #4338ca; font-size: 12px; cursor: pointer; }
        .mb-bot__typing { align-self: flex-start; font-size: 13px; color: #6b7280; padding: 4px 2px; }
        .mb-bot__form { display: flex; gap: 8px; padding: 10px; border-top: 1px solid #e5e7eb; background: #fff; }
        .mb-bot__input { flex: 1; resize: none; border: 1px solid #d1d5db; border-radius: 10px; padding: 8px 10px; font-size: 14px; max-height: 96px; }
        .mb-bot__input:focus { outline: 2px solid #a5b4fc; border-color: #6366f1; }
        .mb-bot__send { border: 0; border-radius: 10px; background: #4f46e5; color: #fff; font-weight: 600; font-size: 14px; padding: 0 14px; cursor: pointer; }
        .mb-bot__send:disabled { opacity: .5; cursor: default; }
        .mb-bot__note { padding: 0 12px 8px; font-size: 11px; color: #9ca3af; background: #fff; }
        @media (min-width: 640px) { .mb-bot { right: 24px; bottom: 24px; } .mb-bot__fab-label { display: block; } .mb-bot__panel { right: 24px; bottom: 24px; } }
    </style>

    <div class="mb-bot" id="mb-bot"
        data-send-url="{{ route('public.chatbot.send') }}"
        data-history-url="{{ route('public.chatbot.history') }}"
        data-support-url="{{ auth()->check() ? route('user.chat.index') : route('public.contact') }}">
        <button type="button" class="mb-bot__fab" data-bot-open aria-label="Uliza Mbunie AI">
            <span class="mb-bot__fab-label">Uliza Mbunie AI</span>
            <span class="mb-bot__fab-btn">
                <picture>
                    <source srcset="{{ asset('images/mbunie-ai.webp') }}" type="image/webp">
                    <img src="{{ asset('images/mbunie-ai.png') }}" alt="" width="48" height="48">
                </picture>
            </span>
        </button>

        <div class="mb-bot__panel" data-bot-panel hidden role="dialog" aria-label="Mbunie AI chat">
            <div class="mb-bot__head">
                <img src="{{ asset('images/mbunie-ai.png') }}" alt="">
                <div>
                    <div class="mb-bot__title">Mbunie AI</div>
                    <div class="mb-bot__sub">Msaidizi wa MbunieEduHub. Kiswahili au English</div>
                </div>
                <button type="button" class="mb-bot__close" data-bot-close aria-label="Funga">&times;</button>
            </div>
            <div class="mb-bot__body" data-bot-body aria-live="polite"></div>
            <form class="mb-bot__form" data-bot-form>
                <textarea class="mb-bot__input" data-bot-input rows="1" maxlength="{{ \App\Support\ChatbotSettings::MAX_MESSAGE_LENGTH }}" placeholder="Andika swali lako..." aria-label="Ujumbe"></textarea>
                <button type="submit" class="mb-bot__send" data-bot-send>Tuma</button>
            </form>
            <div class="mb-bot__note">Majibu yanatolewa na AI na yanaweza kuwa na makosa.</div>
        </div>
    </div>

    <script>
    (function () {
        var root = document.getElementById('mb-bot');
        if (!root || root.dataset.ready) return;
        root.dataset.ready = '1';

        var fab = root.querySelector('[data-bot-open]');
        var panel = root.querySelector('[data-bot-panel]');
        var body = root.querySelector('[data-bot-body]');
        var form = root.querySelector('[data-bot-form]');
        var input = root.querySelector('[data-bot-input]');
        var send = root.querySelector('[data-bot-send]');
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var loaded = false, busy = false;

        function scrollDown() { body.scrollTop = body.scrollHeight; }

        // Plain text with clickable http(s) links; never inserts HTML.
        function addMessage(role, text) {
            var el = document.createElement('div');
            el.className = 'mb-bot__msg ' + (role === 'user' ? 'mb-bot__msg--user' : 'mb-bot__msg--bot');
            String(text).split(/(https?:\/\/[^\s<>"')\]]+)/g).forEach(function (part, i) {
                if (i % 2 === 1) {
                    var a = document.createElement('a');
                    a.href = part.replace(/[.,;:!?]+$/, '');
                    a.textContent = part;
                    a.target = '_blank';
                    a.rel = 'noopener';
                    el.appendChild(a);
                } else if (part) {
                    el.appendChild(document.createTextNode(part));
                }
            });
            body.appendChild(el);
            scrollDown();
        }

        function addSupport(url, label) {
            var a = document.createElement('a');
            a.className = 'mb-bot__support';
            a.href = url || root.dataset.supportUrl;
            a.textContent = label || 'Wasiliana na msaada';
            body.appendChild(a);
            scrollDown();
        }

        function addChips() {
            var wrap = document.createElement('div');
            wrap.className = 'mb-bot__chips';
            ['Bei za AI tools ni kiasi gani?', 'Nalipaje?', 'Scholarships zilizopo', 'How do live classes work?'].forEach(function (q) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'mb-bot__chip';
                b.textContent = q;
                b.addEventListener('click', function () { wrap.remove(); ask(q); });
                wrap.appendChild(b);
            });
            body.appendChild(wrap);
        }

        function welcome() {
            addMessage('bot', 'Habari! Mimi ni Mbunie AI. Niulize kuhusu AI tools, bei, malipo, masomo, research au scholarships.\nHi! Ask me anything about MbunieEduHub.');
            addChips();
        }

        function load() {
            if (loaded) return;
            loaded = true;
            fetch(root.dataset.historyUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : { messages: [] }; })
                .then(function (data) {
                    welcome();
                    (data.messages || []).forEach(function (m) { addMessage(m.role, m.content); });
                })
                .catch(welcome);
        }

        function open() { panel.hidden = false; fab.hidden = true; load(); setTimeout(function () { input.focus(); }, 50); }
        function close() { panel.hidden = true; fab.hidden = false; }

        function ask(text) {
            text = (text || '').trim();
            if (!text || busy) return;
            busy = true; send.disabled = true;
            addMessage('user', text);
            var typing = document.createElement('div');
            typing.className = 'mb-bot__typing';
            typing.textContent = 'Mbunie AI anaandika...';
            body.appendChild(typing); scrollDown();

            fetch(root.dataset.sendUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ message: text })
            }).then(function (r) {
                if (r.status === 429) return { reply: 'Umetuma maswali mengi kwa muda mfupi. Subiri kidogo kisha ujaribu tena.\nToo many messages, please wait a moment.', status: 'limit' };
                if (r.status === 419) return { reply: 'Ukurasa umekaa muda mrefu. Tafadhali pakia upya (refresh) ukurasa.\nPlease refresh the page and try again.', status: 'error' };
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            }).then(function (data) {
                typing.remove();
                addMessage('bot', data.reply);
                if (data.status && data.status !== 'ok') addSupport(data.support_url, data.support_label);
            }).catch(function () {
                typing.remove();
                addMessage('bot', 'Samahani, kuna tatizo la mtandao. Jaribu tena au wasiliana na msaada.\nSorry, something went wrong.');
                addSupport();
            }).finally(function () {
                busy = false; send.disabled = false; input.focus();
            });
        }

        fab.addEventListener('click', open);
        root.querySelector('[data-bot-close]').addEventListener('click', close);
        form.addEventListener('submit', function (e) { e.preventDefault(); var t = input.value; input.value = ''; ask(t); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); }
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) close(); });
    })();
    </script>
@endif
