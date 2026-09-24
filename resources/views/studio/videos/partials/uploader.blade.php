{{--
    Chunked video uploader (learnChunkUploader, resources/js/learning/uploader.js).

    @include('studio.videos.partials.uploader', [
        'config' => [...],          // VideoController::uploaderConfig()
        'label'  => 'Video file',   // optional
        'help'   => '...',          // optional
        'inputId' => 'video-file',  // optional id for the (unnamed) file picker
        'panel'  => 'replace',      // optional: only show upload_token errors when old('_panel') matches
    ])

    Only hidden inputs are submitted with the surrounding form: upload_token,
    duration_seconds, width, height and (autoThumbnail) auto_thumbnail. The
    picker itself has no name, so the video is never posted with the form.
--}}
@php
    $label = $label ?? 'Video file';
    $help = $help ?? null;
    $inputId = $inputId ?? 'upload-'.\Illuminate\Support\Str::random(6);
    $maxMb = (int) floor(($config['maxBytes'] ?? 0) / 1048576);
    $exts = strtoupper(implode(', ', $config['extensions'] ?? []));
@endphp

<div x-data="learnChunkUploader(@js($config))" class="space-y-2" data-learn-uploader>
    <input type="hidden" name="upload_token" :value="token">
    @if ($config['captureMeta'] ?? false)
        <input type="hidden" name="duration_seconds" :value="meta.duration ?? ''">
        <input type="hidden" name="width" :value="meta.width ?? ''">
        <input type="hidden" name="height" :value="meta.height ?? ''">
    @endif
    @if ($config['autoThumbnail'] ?? false)
        <input type="file" name="auto_thumbnail" x-ref="autoThumb" class="hidden" tabindex="-1" aria-hidden="true" accept="image/jpeg">
    @endif

    <div class="flex items-baseline justify-between gap-2">
        <label for="{{ $inputId }}" class="mbui-label">
            {{ $label }}
            @if ($config['required'] ?? false)<span class="text-red-600">*</span>@endif
        </label>
        <span class="text-xs text-gray-400">{{ $exts }} · up to {{ number_format($maxMb) }} MB</span>
    </div>

    <input id="{{ $inputId }}" type="file" x-ref="picker" class="sr-only" accept="{{ $config['accept'] ?? '' }}"
        @change="choose($event.target.files[0]); $event.target.value = ''">

    {{-- Idle: drop zone --}}
    <div x-show="status === 'idle'"
        @dragover.prevent="dragging = true" @dragenter.prevent="dragging = true"
        @dragleave.prevent="dragging = false" @drop.prevent="dragging = false; choose($event.dataTransfer.files[0])"
        :class="dragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-gray-50 hover:border-gray-400'"
        class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-8 text-center transition">
        <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
        </svg>
        <p class="mt-2 text-sm text-gray-700">
            <span class="hidden sm:inline">Drag a video here, or </span>
            <button type="button" class="font-semibold text-indigo-600 hover:text-indigo-500 focus:outline-none focus-visible:underline"
                @click="$refs.picker.click()">choose a file</button>
        </p>
        <p class="mt-1 text-xs text-gray-500">Large files upload in small pieces — you can pause and resume.</p>
    </div>

    {{-- Working / done / error --}}
    <div x-show="status !== 'idle'" x-cloak class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="flex items-start gap-3">
            <div class="h-14 w-24 shrink-0 overflow-hidden rounded bg-gray-100">
                <template x-if="thumbUrl">
                    <img :src="thumbUrl" alt="Captured frame" class="h-full w-full object-cover">
                </template>
                <template x-if="!thumbUrl">
                    <div class="flex h-full w-full items-center justify-center text-gray-400" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
                        </svg>
                    </div>
                </template>
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-gray-900" x-text="fileName"></p>
                <p class="mt-0.5 text-xs text-gray-500">
                    <span x-text="sizeLabel(fileSize)"></span>
                    <template x-if="meta.duration"><span> · <span x-text="durationLabel(meta.duration)"></span></span></template>
                    <template x-if="meta.width && meta.height"><span> · <span x-text="meta.width + '×' + meta.height"></span></span></template>
                </p>

                <div class="mt-1 text-xs" aria-live="polite">
                    <p x-show="status === 'reading'" class="text-gray-500">Preparing…</p>
                    <p x-show="status === 'uploading'" class="text-gray-600">
                        Uploading <span x-text="percent + '%'"></span>
                        <span x-show="speed > 0"> · <span x-text="speedLabel()"></span> · <span x-text="etaLabel()"></span> left</span>
                        <span x-show="retrying" class="text-amber-700"> · connection hiccup, retrying…</span>
                    </p>
                    <p x-show="status === 'paused'" class="text-amber-700">Paused at <span x-text="percent + '%'"></span></p>
                    <p x-show="status === 'completing'" class="text-gray-600">Checking the file…</p>
                    <p x-show="status === 'done'" class="flex items-center gap-1 text-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Uploaded — it will be attached when you save.
                    </p>
                    <p x-show="status === 'error'" class="text-red-700" role="alert" x-text="error"></p>
                </div>
            </div>
        </div>

        <div x-show="['uploading', 'paused', 'completing'].includes(status)" class="mt-3">
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-label="Upload progress"
                aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent">
                <div class="h-full rounded-full transition-all" :class="status === 'paused' ? 'bg-amber-500' : 'bg-indigo-600'"
                    :style="`width: ${percent}%`"></div>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <x-mbui.button variant="secondary" class="!px-3 !py-1.5 text-xs" x-show="status === 'uploading'" @click="pause()">Pause</x-mbui.button>
            <x-mbui.button variant="secondary" class="!px-3 !py-1.5 text-xs" x-show="status === 'paused'" @click="resume()">Resume</x-mbui.button>
            <x-mbui.button variant="secondary" class="!px-3 !py-1.5 text-xs" x-show="status === 'error' && canRetry" @click="retry()">Try again</x-mbui.button>
            <x-mbui.button variant="ghost" class="!px-3 !py-1.5 text-xs text-red-600 hover:bg-red-50" x-show="['uploading', 'paused', 'reading'].includes(status)" @click="cancel()">Cancel upload</x-mbui.button>
            <x-mbui.button variant="ghost" class="!px-3 !py-1.5 text-xs" x-show="['done', 'error'].includes(status)" @click="reset(true); $nextTick(() => $refs.picker.click())">Choose a different file</x-mbui.button>
            <x-mbui.button variant="ghost" class="!px-3 !py-1.5 text-xs" x-show="['done', 'error'].includes(status)" @click="reset(true)">Remove</x-mbui.button>
        </div>
    </div>

    <p x-show="blockedMessage" x-cloak class="text-sm text-red-700" role="alert" x-text="blockedMessage"></p>

    @if ($help)
        <p class="text-xs text-gray-500">{{ $help }}</p>
    @endif

    @if (! isset($panel) || old('_panel') === $panel)
        @error('upload_token')
            <p class="text-sm text-red-700">{{ $message }}</p>
        @enderror
    @endif
</div>
