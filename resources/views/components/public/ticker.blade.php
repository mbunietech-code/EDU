@props(['items' => []])

@php
    $items = collect($items)->filter(fn ($i) => filled($i['text'] ?? null))->values();
    $secondsPerItem = (int) (config('marketing.ticker.seconds_per_item') ?: 4);
    $refreshSeconds = (int) (config('marketing.ticker.refresh_seconds') ?: 20);
    $duration = max(20, $items->count() * $secondsPerItem);

    // Heroicons (outline) — keyword => <path> markup.
    $icons = [
        'bolt'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />',
        'unlock'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        'tool'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26" />',
        'product'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />',
        'scholarship' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443" />',
        'members'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />',
        'shield'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />',
        'star'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />',
    ];
    $iconFor = fn ($k) => $icons[$k] ?? $icons['star'];
@endphp

@if ($items->isNotEmpty())
    <section aria-label="Announcements"
             class="mbui-ticker"
             data-mbui-ticker
             data-feed="{{ route('public.ticker.feed') }}"
             data-refresh="{{ $refreshSeconds }}"
             data-seconds-per-item="{{ $secondsPerItem }}">
        <div class="mbui-ticker__rail">
            <span class="mbui-ticker__label">
                <span class="mbui-ticker__pulse"></span> Live
            </span>
            <div class="mbui-ticker__viewport">
                <div class="mbui-ticker__track" style="--mbui-ticker-duration: {{ $duration }}s;">
                    @foreach ([1, 2] as $pass)
                        <ul class="mbui-ticker__group" @if ($pass === 2) aria-hidden="true" @endif>
                            @foreach ($items as $item)
                                @php $tag = !empty($item['url']) ? 'a' : 'span'; @endphp
                                <li class="mbui-ticker__item">
                                    <{{ $tag }} @if (!empty($item['url'])) href="{{ $item['url'] }}" @endif class="mbui-ticker__card">
                                        <span class="mbui-ticker__chip" data-live="{{ !empty($item['live']) ? '1' : '0' }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                                {!! $iconFor($item['icon'] ?? 'star') !!}
                                            </svg>
                                        </span>
                                        <span class="mbui-ticker__text">{{ $item['text'] }}</span>
                                    </{{ $tag }}>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @once
        <style>
            .mbui-ticker {
                background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
                border-top: 1px solid #e2e8f0;
                border-bottom: 1px solid #e2e8f0;
                overflow: hidden;
            }
            .mbui-ticker__rail {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.9rem 0;
                max-width: 100%;
            }
            .mbui-ticker__label {
                flex: none;
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                margin-left: max(1rem, calc((100% - 72rem) / 2 + 1rem));
                padding: 0.3rem 0.7rem;
                border-radius: 999px;
                background: #fff;
                border: 1px solid #e2e8f0;
                font-size: 0.6875rem;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: #475569;
            }
            .mbui-ticker__pulse {
                width: 7px; height: 7px; border-radius: 999px; background: #22c55e;
                box-shadow: 0 0 0 0 rgba(34,197,94,0.5);
                animation: mbui-ticker-pulse 2s infinite;
            }
            @keyframes mbui-ticker-pulse {
                0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.45); }
                70% { box-shadow: 0 0 0 7px rgba(34,197,94,0); }
                100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
            }
            .mbui-ticker__viewport { position: relative; flex: 1; min-width: 0; overflow: hidden; }
            .mbui-ticker__viewport::before,
            .mbui-ticker__viewport::after {
                content: ""; position: absolute; top: 0; bottom: 0; width: 3rem; z-index: 2; pointer-events: none;
            }
            .mbui-ticker__viewport::before { left: 0;  background: linear-gradient(90deg, #eef2ff, rgba(238,242,255,0)); }
            .mbui-ticker__viewport::after  { right: 0; background: linear-gradient(270deg, #eef2ff, rgba(238,242,255,0)); }

            .mbui-ticker__track {
                display: flex;
                width: max-content;
                animation: mbui-ticker-scroll var(--mbui-ticker-duration, 40s) linear infinite;
            }
            .mbui-ticker:hover .mbui-ticker__track,
            .mbui-ticker:focus-within .mbui-ticker__track { animation-play-state: paused; }

            .mbui-ticker__group { display: flex; align-items: center; gap: 0.75rem; margin: 0; padding: 0 0.375rem; list-style: none; }
            .mbui-ticker__item { flex: none; }

            .mbui-ticker__card {
                display: inline-flex;
                align-items: center;
                gap: 0.625rem;
                padding: 0.5rem 0.9rem 0.5rem 0.5rem;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 0.85rem;
                box-shadow: 0 1px 2px rgba(15,23,42,0.04);
                text-decoration: none;
                white-space: nowrap;
                transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease;
            }
            a.mbui-ticker__card:hover {
                box-shadow: 0 6px 18px rgba(79,70,229,0.12);
                border-color: #c7d2fe;
                transform: translateY(-1px);
            }
            .mbui-ticker__chip {
                flex: none;
                width: 1.9rem; height: 1.9rem;
                display: inline-flex; align-items: center; justify-content: center;
                border-radius: 0.6rem;
                background: #eef2ff;
                color: #4f46e5;
            }
            .mbui-ticker__chip[data-live="1"] { background: #dcfce7; color: #16a34a; }
            .mbui-ticker__chip svg { width: 1.05rem; height: 1.05rem; }
            .mbui-ticker__text { font-size: 0.8125rem; font-weight: 600; color: #334155; }

            @keyframes mbui-ticker-scroll {
                from { transform: translateX(0); }
                to   { transform: translateX(-50%); }
            }
            @media (prefers-reduced-motion: reduce) {
                .mbui-ticker__track { animation: none; }
                .mbui-ticker__viewport { overflow-x: auto; }
                .mbui-ticker__group[aria-hidden="true"] { display: none; }
            }
            @media (max-width: 640px) {
                .mbui-ticker__label { display: none; }
                .mbui-ticker__rail { padding: 0.75rem 0; }
            }
        </style>
    @endonce

    @once
        @push('scripts')
            <script>
                (function () {
                    var root = document.querySelector('[data-mbui-ticker]');
                    if (!root) return;

                    var track = root.querySelector('.mbui-ticker__track');
                    var feedUrl = root.dataset.feed;
                    var refreshMs = Math.max(8, parseInt(root.dataset.refresh || '20', 10)) * 1000;
                    var secondsPerItem = Math.max(2, parseInt(root.dataset.secondsPerItem || '4', 10));
                    var timer = null;

                    var ICONS = {!! \Illuminate\Support\Js::from($icons) !!};

                    function safeUrl(u) {
                        if (!u) return null;
                        if (u.charAt(0) === '/') return u;
                        try {
                            var p = new URL(u, window.location.origin);
                            if (p.protocol === 'http:' || p.protocol === 'https:') return p.href;
                        } catch (e) {}
                        return null;
                    }

                    function buildGroup(items, hidden) {
                        var ul = document.createElement('ul');
                        ul.className = 'mbui-ticker__group';
                        if (hidden) ul.setAttribute('aria-hidden', 'true');

                        items.forEach(function (item) {
                            var li = document.createElement('li');
                            li.className = 'mbui-ticker__item';

                            var url = safeUrl(item.url);
                            var card = document.createElement(url ? 'a' : 'span');
                            card.className = 'mbui-ticker__card';
                            if (url) card.href = url;

                            var chip = document.createElement('span');
                            chip.className = 'mbui-ticker__chip';
                            chip.setAttribute('data-live', item.live ? '1' : '0');
                            chip.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">'
                                + (ICONS[item.icon] || ICONS.star) + '</svg>';

                            var text = document.createElement('span');
                            text.className = 'mbui-ticker__text';
                            text.textContent = item.text || '';

                            card.appendChild(chip);
                            card.appendChild(text);
                            li.appendChild(card);
                            ul.appendChild(li);
                        });
                        return ul;
                    }

                    function render(items) {
                        if (!items || !items.length) return;
                        track.innerHTML = '';
                        track.appendChild(buildGroup(items, false));
                        track.appendChild(buildGroup(items, true));
                        track.style.setProperty('--mbui-ticker-duration',
                            Math.max(20, items.length * secondsPerItem) + 's');
                    }

                    function fetchFeed() {
                        fetch(feedUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                            .then(function (r) { return r.ok ? r.json() : null; })
                            .then(function (data) { if (data && data.items) render(data.items); })
                            .catch(function () {});
                    }

                    function start() { if (!timer) timer = setInterval(fetchFeed, refreshMs); }
                    function stop() { if (timer) { clearInterval(timer); timer = null; } }

                    document.addEventListener('visibilitychange', function () {
                        if (document.hidden) stop(); else { fetchFeed(); start(); }
                    });

                    setTimeout(fetchFeed, 3000);
                    start();
                })();
            </script>
        @endpush
    @endonce
@endif
