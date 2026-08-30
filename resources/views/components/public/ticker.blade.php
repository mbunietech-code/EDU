@props(['items' => []])

@php
    $items = collect($items)->filter(fn ($i) => filled($i['text'] ?? null))->values();
    $secondsPerItem = (int) (config('marketing.ticker.seconds_per_item') ?: 4);
    $refreshSeconds = (int) (config('marketing.ticker.refresh_seconds') ?: 20);
    $duration = max(18, $items->count() * $secondsPerItem);
@endphp

@if ($items->isNotEmpty())
    <section aria-label="Announcements"
             class="mbui-ticker"
             data-mbui-ticker
             data-feed="{{ route('public.ticker.feed') }}"
             data-refresh="{{ $refreshSeconds }}"
             data-seconds-per-item="{{ $secondsPerItem }}">
        <div class="mbui-ticker__viewport">
            <div class="mbui-ticker__track" style="--mbui-ticker-duration: {{ $duration }}s;">
                {{-- Rendered twice so the scroll animation can loop seamlessly. --}}
                @foreach ([1, 2] as $pass)
                    <ul class="mbui-ticker__group" @if ($pass === 2) aria-hidden="true" @endif>
                        @foreach ($items as $item)
                            <li class="mbui-ticker__item">
                                @if (!empty($item['url']))
                                    <a href="{{ $item['url'] }}" class="mbui-ticker__link">
                                        <span class="mbui-ticker__icon">{{ $item['icon'] ?? '•' }}</span>
                                        <span>{{ $item['text'] }}</span>
                                    </a>
                                @else
                                    <span class="mbui-ticker__link">
                                        <span class="mbui-ticker__icon">{{ $item['icon'] ?? '•' }}</span>
                                        <span>{{ $item['text'] }}</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>
    </section>

    @once
        <style>
            .mbui-ticker {
                overflow: hidden;
                background-color: #eef2ffb3;      /* indigo-50 @ ~70% */
                border-top: 1px solid #e0e7ff;    /* indigo-100 */
                border-bottom: 1px solid #e0e7ff;
            }
            .mbui-ticker__viewport { position: relative; }
            .mbui-ticker__track {
                display: flex;
                width: max-content;
                animation: mbui-ticker-scroll var(--mbui-ticker-duration, 40s) linear infinite;
            }
            .mbui-ticker:hover .mbui-ticker__track,
            .mbui-ticker:focus-within .mbui-ticker__track { animation-play-state: paused; }
            .mbui-ticker__group {
                display: flex;
                align-items: center;
                flex: none;
                margin: 0;
                padding: 0;
                list-style: none;
            }
            .mbui-ticker__item { flex: none; }
            .mbui-ticker__link {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.6rem 1.75rem;
                font-size: 0.8125rem;
                font-weight: 500;
                white-space: nowrap;
                color: #3730a3;                 /* indigo-800 */
                text-decoration: none;
            }
            a.mbui-ticker__link:hover { color: #4f46e5; text-decoration: underline; }
            .mbui-ticker__item + .mbui-ticker__item .mbui-ticker__link { border-left: 1px solid #c7d2fe; }
            .mbui-ticker__icon { font-size: 0.95rem; line-height: 1; }

            @keyframes mbui-ticker-scroll {
                from { transform: translateX(0); }
                to   { transform: translateX(-50%); }
            }

            @media (prefers-reduced-motion: reduce) {
                .mbui-ticker__track { animation: none; }
                .mbui-ticker__viewport { overflow-x: auto; }
                .mbui-ticker__group[aria-hidden="true"] { display: none; }
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

                    function safeUrl(u) {
                        if (!u) return null;
                        if (u.charAt(0) === '/') return u;
                        try {
                            var parsed = new URL(u, window.location.origin);
                            if (parsed.protocol === 'http:' || parsed.protocol === 'https:') return parsed.href;
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
                            var link = document.createElement(url ? 'a' : 'span');
                            link.className = 'mbui-ticker__link';
                            if (url) link.href = url;

                            var icon = document.createElement('span');
                            icon.className = 'mbui-ticker__icon';
                            icon.textContent = item.icon || '•';

                            var text = document.createElement('span');
                            text.textContent = item.text || '';

                            link.appendChild(icon);
                            link.appendChild(text);
                            li.appendChild(link);
                            ul.appendChild(li);
                        });
                        return ul;
                    }

                    function render(items) {
                        if (!items || !items.length) return;
                        track.innerHTML = '';
                        track.appendChild(buildGroup(items, false));
                        track.appendChild(buildGroup(items, true));
                        var duration = Math.max(18, items.length * secondsPerItem);
                        track.style.setProperty('--mbui-ticker-duration', duration + 's');
                    }

                    function fetchFeed() {
                        fetch(feedUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                            .then(function (r) { return r.ok ? r.json() : null; })
                            .then(function (data) { if (data && data.items) render(data.items); })
                            .catch(function () { /* keep showing what we have */ });
                    }

                    function start() {
                        if (timer) return;
                        timer = setInterval(fetchFeed, refreshMs);
                    }
                    function stop() {
                        if (timer) { clearInterval(timer); timer = null; }
                    }

                    document.addEventListener('visibilitychange', function () {
                        if (document.hidden) { stop(); } else { fetchFeed(); start(); }
                    });

                    // First refresh shortly after load, then on the interval.
                    setTimeout(fetchFeed, 3000);
                    start();
                })();
            </script>
        @endpush
    @endonce
@endif
