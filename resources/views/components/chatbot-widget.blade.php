@php
    try {
        $chatbotLive = app(\App\Support\ChatbotSettings::class)->isLive();
    } catch (\Throwable $e) {
        $chatbotLive = false;
    }
@endphp

@if ($chatbotLive)
    {{-- Styled inline on purpose: works even before the next asset build. Colours follow the MbunieHub logo (navy, blue, cyan). --}}
    <style>
        .mb-bot { --mbh-navy: #1b2471; --mbh-blue: #1565c0; --mbh-cyan: #00b4f0; --mbh-ink: #0f1d4a; position: fixed; right: 16px; bottom: 16px; z-index: 9998; font-family: inherit; }
        .mb-bot [hidden] { display: none !important; }
        .mb-bot__fab { display: flex; align-items: center; gap: 10px; border: 0; background: none; padding: 0; cursor: pointer; }
        .mb-bot__fab-label { display: none; padding: 8px 12px; border-radius: 12px; background: #fff; color: var(--mbh-ink); font-size: 14px; font-weight: 600; box-shadow: 0 10px 25px rgba(15,29,74,.18); border: 1px solid #dbe4f5; }
        .mb-bot__fab:focus-visible { outline: 3px solid #a5e3f9; outline-offset: 4px; border-radius: 16px; }
        .mb-bot__fab-btn { display: block; width: 60px; transition: transform .15s; }
        .mb-bot__fab:hover .mb-bot__fab-btn { transform: scale(1.08); }
        .mb-bot__panel { position: fixed; right: 16px; bottom: 16px; width: min(380px, calc(100vw - 32px)); height: min(580px, calc(100vh - 32px)); display: flex; flex-direction: column; background: #fff; border-radius: 18px; box-shadow: 0 24px 60px rgba(15,29,74,.35); border: 1px solid #dbe4f5; overflow: hidden; }
        .mb-bot__head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: linear-gradient(135deg, var(--mbh-navy) 0%, var(--mbh-blue) 60%, var(--mbh-cyan) 100%); color: #fff; }
        .mb-bot__head img { width: 42px; height: 42px; border-radius: 9999px; border: 2px solid rgba(255,255,255,.75); background: #0b1430; object-fit: cover; display: block; }
        .mb-bot__title { font-size: 15px; font-weight: 700; line-height: 1.2; letter-spacing: .01em; }
        .mb-bot__sub { font-size: 12px; opacity: .9; display: flex; align-items: center; gap: 6px; }
        .mb-bot__dot { width: 7px; height: 7px; border-radius: 9999px; background: #5eead4; box-shadow: 0 0 0 2px rgba(94,234,212,.3); }
        .mb-bot__close { margin-left: auto; background: rgba(255,255,255,.15); border: 0; color: #fff; font-size: 20px; line-height: 1; cursor: pointer; width: 32px; height: 32px; border-radius: 9999px; }
        .mb-bot__close:hover { background: rgba(255,255,255,.28); }
        .mb-bot__body { flex: 1; overflow-y: auto; padding: 14px; background: linear-gradient(180deg, #f3f7fd 0%, #eef4fc 100%); display: flex; flex-direction: column; gap: 10px; }
        .mb-bot__row { align-self: flex-start; display: flex; align-items: flex-end; gap: 8px; max-width: 92%; }
        .mb-bot__row img { flex: none; width: 28px; height: 28px; border-radius: 9999px; object-fit: cover; box-shadow: 0 1px 3px rgba(15,29,74,.25); }
        .mb-bot__msg { padding: 9px 12px; border-radius: 14px; font-size: 14px; line-height: 1.45; white-space: pre-line; word-wrap: break-word; overflow-wrap: anywhere; }
        .mb-bot__msg--bot { background: #fff; color: #1f2937; border: 1px solid #dbe4f5; border-bottom-left-radius: 4px; box-shadow: 0 1px 2px rgba(15,29,74,.06); }
        .mb-bot__msg--user { align-self: flex-end; max-width: 85%; background: linear-gradient(135deg, var(--mbh-navy), var(--mbh-blue)); color: #fff; border-bottom-right-radius: 4px; }
        .mb-bot__msg a { color: var(--mbh-blue); text-decoration: underline; }
        .mb-bot__msg--user a { color: #fff; }
        .mb-bot__support { align-self: flex-start; margin-left: 36px; display: inline-block; padding: 7px 12px; border-radius: 9999px; background: #e6f6fd; color: var(--mbh-navy); font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid #a5e3f9; }
        .mb-bot__chips { display: flex; flex-wrap: wrap; gap: 6px; margin-left: 36px; }
        .mb-bot__chip { padding: 6px 10px; border-radius: 9999px; background: #fff; border: 1px solid #b9d3f3; color: var(--mbh-navy); font-size: 12px; cursor: pointer; }
        .mb-bot__chip:hover { border-color: var(--mbh-cyan); background: #e6f6fd; }
        .mb-bot__typing { align-self: flex-start; margin-left: 36px; font-size: 13px; color: #64748b; padding: 2px; }
        .mb-bot__form { display: flex; gap: 8px; padding: 10px; border-top: 1px solid #dbe4f5; background: #fff; }
        .mb-bot__input { flex: 1; resize: none; border: 1px solid #cbd5e1; border-radius: 12px; padding: 8px 10px; font-size: 14px; max-height: 96px; }
        .mb-bot__input:focus { outline: 2px solid #a5e3f9; border-color: var(--mbh-cyan); }
        .mb-bot__send { border: 0; border-radius: 12px; background: linear-gradient(135deg, var(--mbh-blue), var(--mbh-cyan)); color: #fff; font-weight: 700; font-size: 14px; padding: 0 16px; cursor: pointer; }
        .mb-bot__send:disabled { opacity: .5; cursor: default; }
        .mb-bot__note { padding: 0 12px 8px; font-size: 11px; color: #94a3b8; background: #fff; }
        @media (min-width: 640px) { .mb-bot { right: 24px; bottom: 24px; } .mb-bot__fab-label { display: block; } .mb-bot__panel { right: 24px; bottom: 24px; } }

        /* Robot mascot: floats, waves and its antenna glows. CSS only. */
        .mb-bot__buddy { display: block; width: 100%; animation: mbh-bob 3.2s ease-in-out infinite; }
        .mb-bot__buddy img { filter: drop-shadow(0 8px 12px rgba(15,29,74,.35)); }
        .mb-bot__tilt { position: relative; display: block; transform-origin: 50% 90%; animation: mbh-wave 4.5s ease-in-out infinite; }
        .mb-bot__tilt img { display: block; width: 100%; height: auto; filter: drop-shadow(0 6px 8px rgba(15,29,74,.3)); }
        .mb-bot__glow { position: absolute; left: 58.1%; top: 2.9%; width: 22%; aspect-ratio: 1; border-radius: 9999px; transform: translate(-50%, -50%); background: radial-gradient(circle, rgba(165,243,252,.95) 0%, rgba(0,180,240,.55) 45%, rgba(0,180,240,0) 72%); animation: mbh-glow 1.8s ease-in-out infinite; }
        .mb-bot__head .mb-bot__face { flex: none; width: 42px; height: 42px; border-radius: 9999px; border: 2px solid rgba(255,255,255,.75); display: block; animation: mbh-bob 3.2s ease-in-out infinite; }
        .mb-bot__hero { align-self: center; position: relative; width: 84px; margin: 4px 0 2px; animation: mbh-bob 3.2s ease-in-out infinite; }
        .mb-bot__hero .mb-bot__tilt { animation-duration: 3.6s; }
        @media (min-width: 640px) { .mb-bot__fab-btn { width: 72px; } }
        @keyframes mbh-bob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
        @keyframes mbh-wave { 0%, 55%, 100% { transform: rotate(0deg); } 65% { transform: rotate(-9deg); } 75% { transform: rotate(7deg); } 85% { transform: rotate(-5deg); } 93% { transform: rotate(3deg); } }
        @keyframes mbh-glow { 0%, 100% { opacity: .5; transform: translate(-50%, -50%) scale(.8); } 50% { opacity: 1; transform: translate(-50%, -50%) scale(1.3); } }
        @media (prefers-reduced-motion: reduce) { .mb-bot__buddy, .mb-bot__tilt, .mb-bot__glow, .mb-bot__face, .mb-bot__hero { animation: none !important; } }
    </style>

    <div class="mb-bot" id="mb-bot"
        data-send-url="{{ route('public.chatbot.send') }}"
        data-history-url="{{ route('public.chatbot.history') }}"
        data-support-url="{{ auth()->check() ? route('user.chat.index') : route('public.contact') }}"
        data-avatar="{{ asset('images/chatbot/mbuniehub-robot-head.webp') }}">
        <button type="button" class="mb-bot__fab" data-bot-open aria-label="Fungua chat na MbunieHub AI" title="Ongea na MbunieHub AI">
            <span class="mb-bot__fab-label">Uliza MbunieHub AI</span>
            <span class="mb-bot__fab-btn">
                <span class="mb-bot__buddy" aria-hidden="true">
                    <span class="mb-bot__tilt">
                        <picture>
                            <source srcset="{{ asset('images/chatbot/mbuniehub-robot.webp') }}" type="image/webp">
                            <img src="{{ asset('images/chatbot/mbuniehub-robot.png') }}" alt="" width="72" height="98">
                        </picture>
                        <span class="mb-bot__glow"></span>
                    </span>
                </span>
            </span>
        </button>

        <div class="mb-bot__panel" data-bot-panel hidden role="dialog" aria-label="MbunieHub AI chat">
            <div class="mb-bot__head">
                <picture>
                    <source srcset="{{ asset('images/chatbot/mbuniehub-robot-head.webp') }}" type="image/webp">
                    <img class="mb-bot__face" src="{{ asset('images/chatbot/mbuniehub-robot-head.png') }}" alt="" width="42" height="42">
                </picture>
                <div>
                    <div class="mb-bot__title">MbunieHub AI</div>
                    <div class="mb-bot__sub"><span class="mb-bot__dot"></span>Msaidizi wako. Kiswahili au English</div>
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

        <template data-bot-hero>
            <div class="mb-bot__hero" aria-hidden="true">
                <span class="mb-bot__tilt">
                    <picture>
                        <source srcset="{{ asset('images/chatbot/mbuniehub-robot.webp') }}" type="image/webp">
                        <img src="{{ asset('images/chatbot/mbuniehub-robot.png') }}" alt="" width="84" height="114">
                    </picture>
                    <span class="mb-bot__glow"></span>
                </span>
            </div>
        </template>
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
            if (role === 'user') {
                body.appendChild(el);
            } else {
                var row = document.createElement('div');
                row.className = 'mb-bot__row';
                var av = document.createElement('img');
                av.src = root.dataset.avatar;
                av.alt = '';
                row.appendChild(av);
                row.appendChild(el);
                body.appendChild(row);
            }
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
            var hero = root.querySelector('[data-bot-hero]');
            if (hero && hero.content) body.appendChild(hero.content.cloneNode(true));
            addMessage('bot', 'Habari! Mimi ni MbunieHub AI. Niulize kuhusu AI tools, bei, malipo, masomo, research au scholarships.\nHi! Ask me anything about MbunieHub.');
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
            typing.textContent = 'MbunieHub AI anaandika...';
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
