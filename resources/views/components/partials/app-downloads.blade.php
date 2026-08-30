@php $downloads = \App\Support\AppDownloads::available(); @endphp

@if (! empty($downloads))
    @php
        $icons = [
            'android' => '<path d="M6 18c0 .55.45 1 1 1h1v3.5a1.5 1.5 0 003 0V19h2v3.5a1.5 1.5 0 003 0V19h1c.55 0 1-.45 1-1V8H6v10zM3.5 8A1.5 1.5 0 002 9.5v7a1.5 1.5 0 003 0v-7A1.5 1.5 0 003.5 8zm17 0A1.5 1.5 0 0019 9.5v7a1.5 1.5 0 003 0v-7A1.5 1.5 0 0020.5 8zM15.5 3.9l1.3-1.3a.5.5 0 00-.7-.7l-1.5 1.5A5.9 5.9 0 0012 3c-.9 0-1.7.2-2.4.5L8.1 2a.5.5 0 00-.7.7l1.3 1.3A5.9 5.9 0 006 7h12a5.9 5.9 0 00-2.5-3.1zM9.5 5.5a.75.75 0 110-1.5.75.75 0 010 1.5zm5 0a.75.75 0 110-1.5.75.75 0 010 1.5z"/>',
            'windows' => '<path d="M3 5.6l7.2-1v7.1H3V5.6zm0 12.8l7.2 1v-7H3v6zM11 4.4L21 3v9.3h-10V4.4zM11 19.6L21 21v-8.7h-10v7.3z"/>',
            'macos' => '<path d="M16.4 12.6c0-2.5 2-3.7 2.1-3.8-1.1-1.7-2.9-1.9-3.5-1.9-1.5-.2-2.9.9-3.6.9-.8 0-1.9-.9-3.1-.8-1.6 0-3 .9-3.8 2.4-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7c1.3 0 2.1-1.1 2.9-2.3.9-1.3 1.3-2.6 1.3-2.7-.1 0-2.5-1-2.5-3.6zM14.5 5.3c.7-.8 1.1-2 1-3.2-1 0-2.2.7-2.9 1.5-.6.7-1.2 1.9-1 3 1.1.1 2.2-.5 2.9-1.3z"/>',
            'linux' => '<path d="M12 2c-2 0-3 2-3 4 0 1.4.3 2 .3 3 0 1-1.3 2-2.3 3.8-1 1.8-1.7 3.6-2.3 4.4-.5.8-1 1-1 1.8 0 .8.7 1.2 1.6 1.4.9.2 1.7.7 2.4 1.2.7.5 1.5.9 2.6.9 1.7 0 2.9-1 4.1-1s2.4 1 4.1 1c1.1 0 1.9-.4 2.5-.9.7-.5 1.5-1 2.4-1.2.9-.2 1.6-.6 1.6-1.4 0-.8-.5-1-1-1.8-.6-.8-1.3-2.6-2.3-4.4-1-1.8-2.3-2.8-2.3-3.8 0-1 .3-1.6.3-3 0-2-1-4-3-4h-3zm.5 4.2c.5 0 .9.5.9 1.1s-.4 1.1-.9 1.1-.9-.5-.9-1.1.4-1.1.9-1.1zm-2.9.1c.4 0 .7.4.7 1s-.3 1-.7 1-.7-.4-.7-1 .3-1 .7-1z"/>',
        ];
    @endphp
    <div class="border-t border-gray-200 bg-gray-900">
        <div class="mbui-container py-10">
            <div class="flex flex-col items-center gap-2 text-center">
                <h3 class="text-lg font-bold text-white">Get the MHub app</h3>
                <p class="text-sm text-gray-400">Manage your access from your phone or computer.</p>
            </div>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @foreach ($downloads as $d)
                    <a href="{{ $d['url'] }}"
                       @if (\Illuminate\Support\Str::startsWith($d['url'], ['http://', 'https://']) && ! \Illuminate\Support\Str::contains($d['url'], request()->getHost()))
                           target="_blank" rel="noopener"
                       @endif
                       class="inline-flex items-center gap-3 rounded-xl bg-white/10 px-5 py-3 text-white ring-1 ring-inset ring-white/15 transition hover:bg-white/15">
                        <svg class="h-6 w-6 flex-none" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            {!! $icons[$d['platform']] ?? '' !!}
                        </svg>
                        <span class="text-left leading-tight">
                            <span class="block text-[11px] uppercase tracking-wide text-gray-400">Download for</span>
                            <span class="block text-sm font-semibold">
                                {{ $d['label'] }}@if (! empty($d['version'])) <span class="font-normal text-gray-400">{{ $d['version'] }}</span>@endif
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
