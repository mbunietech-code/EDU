@props(['items' => []])

@php
    $items = collect($items)->filter(fn ($i) => filled($i['text'] ?? null))->values();
    $secondsPerItem = (int) (config('marketing.ticker.seconds_per_item') ?: 4);
    // One full loop scrolls the whole (non-duplicated) list past once.
    $duration = max(18, $items->count() * $secondsPerItem);
@endphp

@if ($items->isNotEmpty())
    <section aria-label="Announcements" class="mbui-ticker">
        <div class="mbui-ticker__viewport">
            <div class="mbui-ticker__track" style="--mbui-ticker-duration: {{ $duration }}s;">
                {{-- The list is rendered twice so the animation can loop seamlessly. --}}
                @foreach ([1, 2] as $pass)
                    <ul class="mbui-ticker__group" @if($pass === 2) aria-hidden="true" @endif>
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
@endif
