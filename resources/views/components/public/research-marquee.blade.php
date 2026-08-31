@props(['items' => []])

@php
    $items = collect($items)->filter(fn ($i) => filled($i['title'] ?? null))->values();
    $duration = max(30, $items->count() * 7);
@endphp

@if ($items->isNotEmpty())
    <section aria-label="Published research" class="rs-marquee">
        <div class="mbui-container text-center">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">From the research library</h2>
            <p class="mt-1 text-sm text-gray-500">Work published by our contributors. Sign in to read in full.</p>
        </div>

        <div class="rs-marquee__viewport">
            <div class="rs-marquee__track" style="--rs-duration: {{ $duration }}s;">
                @foreach ([1, 2] as $pass)
                    <ul class="rs-marquee__group" @if ($pass === 2) aria-hidden="true" @endif>
                        @foreach ($items as $item)
                            <li class="rs-marquee__item">
                                <a href="{{ $item['url'] }}" class="rs-marquee__card">
                                    @if (!empty($item['category']))
                                        <span class="rs-marquee__chip">{{ $item['category'] }}</span>
                                    @endif
                                    <span class="rs-marquee__title">{{ $item['title'] }}</span>
                                    <span class="rs-marquee__meta">{{ $item['author'] ?? 'Contributor' }} · {{ $item['chapters'] ?? 0 }} {{ \Illuminate\Support\Str::plural('chapter', $item['chapters'] ?? 0) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>

        <div class="mbui-container text-center">
            <a href="{{ route('research.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">Browse all research &rarr;</a>
        </div>
    </section>

    @once
    <style>
        .rs-marquee { background:#fff; border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; padding:2.5rem 0; overflow:hidden; }
        .rs-marquee__viewport { position:relative; overflow:hidden; margin:1.5rem 0; }
        .rs-marquee__viewport::before, .rs-marquee__viewport::after {
            content:""; position:absolute; top:0; bottom:0; width:6rem; z-index:2; pointer-events:none;
        }
        .rs-marquee__viewport::before { left:0;  background:linear-gradient(90deg,#fff,rgba(255,255,255,0)); }
        .rs-marquee__viewport::after  { right:0; background:linear-gradient(270deg,#fff,rgba(255,255,255,0)); }
        .rs-marquee__track { display:flex; width:max-content; animation:rs-marquee-scroll var(--rs-duration,40s) linear infinite; }
        .rs-marquee:hover .rs-marquee__track { animation-play-state:paused; }
        .rs-marquee__group { display:flex; gap:1rem; margin:0; padding:0 .5rem 0 0; list-style:none; }
        .rs-marquee__item { flex:none; }
        .rs-marquee__card {
            display:flex; flex-direction:column; gap:.4rem; width:19rem; height:100%;
            padding:1rem 1.15rem; background:#fff; border:1px solid #e5e7eb; border-radius:1rem;
            box-shadow:0 1px 2px rgba(15,23,42,.04); text-decoration:none;
            transition:box-shadow .15s ease, transform .15s ease, border-color .15s ease;
        }
        .rs-marquee__card:hover { box-shadow:0 10px 24px rgba(79,70,229,.12); border-color:#c7d2fe; transform:translateY(-2px); }
        .rs-marquee__chip {
            align-self:flex-start; font-size:.6875rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
            color:#4f46e5; background:#eef2ff; padding:.2rem .5rem; border-radius:.5rem;
        }
        .rs-marquee__title { font-size:.95rem; font-weight:700; line-height:1.35; color:#111827; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .rs-marquee__meta { font-size:.75rem; color:#9ca3af; margin-top:auto; }
        @keyframes rs-marquee-scroll { from { transform:translateX(0); } to { transform:translateX(-50%); } }
        @media (prefers-reduced-motion: reduce) {
            .rs-marquee__track { animation:none; }
            .rs-marquee__viewport { overflow-x:auto; scrollbar-width:none; }
            .rs-marquee__viewport::-webkit-scrollbar { display:none; }
            .rs-marquee__group[aria-hidden="true"] { display:none; }
        }
    </style>
    @endonce
@endif
