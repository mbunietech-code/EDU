{{-- Lesson resources. Expects $video (resources loaded). --}}
@if ($video->resources->isNotEmpty())
    <section class="mbui-card p-5" aria-labelledby="lesson-resources">
        <h2 id="lesson-resources" class="mbui-section-label">Resources</h2>
        <ul class="mt-3 divide-y divide-gray-100">
            @foreach ($video->resources as $resource)
                @php($safeLink = $resource->isLink() && preg_match('#^https?://#i', (string) $resource->url))
                <li class="flex items-center gap-3 py-2.5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500" aria-hidden="true">
                        @if ($resource->isLink())
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                            </svg>
                        @else
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900">{{ $resource->title }}</p>
                        <p class="truncate text-xs text-gray-500">
                            @if ($resource->isLink())
                                {{ parse_url((string) $resource->url, PHP_URL_HOST) ?: 'External link' }}
                            @else
                                {{ strtoupper(pathinfo((string) ($resource->original_name ?: $resource->path), PATHINFO_EXTENSION)) ?: 'File' }} · {{ $resource->sizeLabel() }}
                            @endif
                        </p>
                    </div>
                    @if ($resource->isFile() && $resource->path)
                        <a href="{{ route('learn.videos.resource', [$video, $resource]) }}"
                            class="inline-flex shrink-0 items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
                            aria-label="Download {{ $resource->title }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <span class="hidden sm:inline">Download</span>
                        </a>
                    @elseif ($safeLink)
                        <a href="{{ $resource->url }}" target="_blank" rel="noopener noreferrer nofollow"
                            class="inline-flex shrink-0 items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
                            aria-label="Open {{ $resource->title }} (opens in a new tab)">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                            <span class="hidden sm:inline">Open</span>
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
