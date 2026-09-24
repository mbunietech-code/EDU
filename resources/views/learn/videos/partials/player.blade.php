{{-- Lesson player. Expects $video, $canPlay, $config (null unless playable), $next. --}}
@if (! $video->hasFile())
    <div class="flex aspect-video w-full flex-col items-center justify-center rounded-xl bg-gray-900 px-6 text-center text-white" role="status">
        <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="mt-3 text-base font-semibold">This lesson is being prepared</p>
        <p class="mt-1 max-w-md text-sm text-gray-300">The video has not been uploaded yet. Please check back soon.</p>
    </div>
@elseif (! $canPlay || ! $config)
    <div class="flex aspect-video w-full flex-col items-center justify-center rounded-xl bg-gray-900 px-6 text-center text-white" role="status">
        <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
        </svg>
        <p class="mt-3 text-base font-semibold">Playback is not available yet</p>
        <p class="mt-1 max-w-md text-sm text-gray-300">This lesson is not published. Only its instructor and content managers can play it.</p>
    </div>
@else
    @php($firstSource = $config['sources'][0])
    <div x-data="learnVideoPlayer(@js($config))" class="space-y-3">
        <div x-ref="stage" class="relative overflow-hidden rounded-xl bg-black">
            <video x-ref="video" class="block aspect-video w-full bg-black" controls playsinline preload="metadata"
                controlsList="nodownload" oncontextmenu="return false;"
                @if ($video->thumbnailUrl()) poster="{{ $video->thumbnailUrl() }}" @endif
                aria-label="{{ $video->title }}">
                <source src="{{ $firstSource['url'] }}" type="{{ $firstSource['type'] ?? 'video/mp4' }}">
                <p class="p-4 text-sm text-white">Your browser cannot play this video.</p>
            </video>

            {{-- Loading --}}
            <div x-show="loading && ! errorMessage && ! resumePrompt" x-cloak
                class="pointer-events-none absolute inset-0 flex items-center justify-center" role="status">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-black/50">
                    <svg class="h-8 w-8 animate-spin text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                    </svg>
                </span>
                <span class="sr-only">Loading video…</span>
            </div>

            {{-- Error --}}
            <div x-show="errorMessage" x-cloak
                class="absolute inset-0 flex flex-col items-center justify-center bg-gray-900/95 px-6 text-center text-white" role="alert">
                <svg class="h-10 w-10 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <p class="mt-3 max-w-md text-sm" x-text="errorMessage"></p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <x-mbui.button @click="retry()">Retry</x-mbui.button>
                </div>
                <p x-show="sources.length > 1" class="mt-3 text-xs text-gray-300">You can also try another quality below.</p>
            </div>

            {{-- Resume prompt --}}
            <div x-show="resumePrompt && ! errorMessage" x-cloak
                class="absolute inset-0 flex items-center justify-center bg-black/60 p-4">
                <div class="w-full max-w-xs rounded-xl bg-white p-5 text-center shadow-xl" role="dialog" aria-label="Resume lesson">
                    <p class="text-sm font-semibold text-gray-900">Welcome back</p>
                    <p class="mt-1 text-sm text-gray-600">You stopped at <span class="font-medium tabular-nums" x-text="resumeLabel"></span>.</p>
                    <div class="mt-4 flex flex-col gap-2">
                        <x-mbui.button @click="resume()">Continue from <span class="ml-1 tabular-nums" x-text="resumeLabel"></span></x-mbui.button>
                        <x-mbui.button variant="secondary" @click="startOver()">Start over</x-mbui.button>
                    </div>
                </div>
            </div>

            {{-- Up next / end of lesson --}}
            <div x-show="ended && ! errorMessage" x-cloak
                class="absolute inset-0 flex flex-col items-center justify-center bg-gray-900/90 px-6 text-center text-white">
                <template x-if="cfg.nextUrl">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-300">Up next</p>
                        <p class="mt-1 line-clamp-2 max-w-md text-base font-semibold" x-text="cfg.nextTitle || 'Next lesson'"></p>
                        <div class="mt-4 flex flex-wrap justify-center gap-2">
                            <a :href="cfg.nextUrl" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                Play next lesson
                            </a>
                            <button type="button" @click="restart()" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white ring-1 ring-inset ring-white/40 hover:bg-white/10">Replay</button>
                        </div>
                    </div>
                </template>
                <template x-if="! cfg.nextUrl">
                    <div>
                        <p class="text-base font-semibold">You reached the end of this lesson</p>
                        <div class="mt-4 flex flex-wrap justify-center gap-2">
                            <button type="button" @click="restart()" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white ring-1 ring-inset ring-white/40 hover:bg-white/10">Replay</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Toolbar --}}
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Speed --}}
                <div class="relative" @click.outside="speedOpen = false" @keydown.escape="speedOpen = false">
                    <button type="button" @click="speedOpen = ! speedOpen; qualityOpen = false"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        aria-haspopup="true" :aria-expanded="speedOpen.toString()" aria-label="Playback speed">
                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 010 1.954l-7.108 4.061A1.125 1.125 0 013 16.811V8.69zM12.75 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 010 1.954l-7.108 4.061a1.125 1.125 0 01-1.683-.977V8.69z" />
                        </svg>
                        <span class="tabular-nums" x-text="speed + 'x'">1x</span>
                    </button>
                    <div x-show="speedOpen" x-cloak x-transition.opacity
                        class="absolute bottom-full left-0 z-20 mb-2 w-32 rounded-lg bg-white py-1 shadow-lg ring-1 ring-gray-200" role="menu">
                        <template x-for="rate in speeds" :key="rate">
                            <button type="button" role="menuitemradio" :aria-checked="(rate === speed).toString()" @click="setSpeed(rate)"
                                class="flex w-full items-center justify-between px-3 py-1.5 text-left text-sm hover:bg-gray-50"
                                :class="rate === speed ? 'font-semibold text-indigo-600' : 'text-gray-700'">
                                <span x-text="rate === 1 ? 'Normal' : rate + 'x'"></span>
                                <svg x-show="rate === speed" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Quality --}}
                <div class="relative" x-show="sources.length > 1" @click.outside="qualityOpen = false" @keydown.escape="qualityOpen = false">
                    <button type="button" @click="qualityOpen = ! qualityOpen; speedOpen = false"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        aria-haspopup="true" :aria-expanded="qualityOpen.toString()" aria-label="Video quality">
                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                        </svg>
                        <span x-text="currentLabel">{{ $firstSource['label'] }}</span>
                    </button>
                    <div x-show="qualityOpen" x-cloak x-transition.opacity
                        class="absolute bottom-full left-0 z-20 mb-2 w-44 rounded-lg bg-white py-1 shadow-lg ring-1 ring-gray-200" role="menu">
                        <template x-for="source in sources" :key="source.id">
                            <button type="button" role="menuitemradio" :aria-checked="(source.id === currentSource).toString()" @click="setQuality(source.id)"
                                class="flex w-full items-center justify-between px-3 py-1.5 text-left text-sm hover:bg-gray-50"
                                :class="source.id === currentSource ? 'font-semibold text-indigo-600' : 'text-gray-700'">
                                <span x-text="source.label"></span>
                                <svg x-show="source.id === currentSource" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>

                <button type="button" @click="restart()" title="Restart lesson" aria-label="Restart lesson"
                    class="inline-flex items-center rounded-lg bg-white p-2 text-gray-600 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </button>
                <button type="button" @click="toggleFullscreen()" title="Fullscreen (F)" aria-label="Toggle fullscreen"
                    class="inline-flex items-center rounded-lg bg-white p-2 text-gray-600 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                    </svg>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="toggleCompleted()" :disabled="completing" :aria-pressed="completed.toString()"
                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm transition disabled:cursor-wait disabled:opacity-60 sm:flex-none"
                    :class="completed ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/30 hover:bg-emerald-100' : 'bg-white text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span x-text="completed ? 'Completed · Mark as not completed' : 'Mark as completed'">Mark as completed</span>
                </button>
                @if ($next)
                    <x-mbui.btn-link :href="route('learn.videos.show', $next)" class="flex-1 sm:flex-none">
                        Next lesson
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </x-mbui.btn-link>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p x-show="notice" x-cloak x-text="notice" role="status" aria-live="polite"
                class="text-sm font-medium" :class="noticeError ? 'text-red-600' : 'text-emerald-700'"></p>
            <p class="ml-auto hidden text-xs text-gray-400 md:block">
                Shortcuts: <kbd class="font-sans">Space</kbd>/<kbd class="font-sans">K</kbd> play · <kbd class="font-sans">←</kbd>/<kbd class="font-sans">→</kbd> 5 s · <kbd class="font-sans">F</kbd> fullscreen
            </p>
        </div>
    </div>
@endif
