{{-- Extra qualities of a lesson. Expects: $video (renditions loaded), $renditionUploader, $qualities. --}}
<x-mbui.card id="renditions" class="scroll-mt-24">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Qualities</h2>
            <p class="mt-1 text-sm text-gray-500">
                Add pre-encoded copies (for example 360p for slow connections). Learners can switch quality in the player.
                The original file plays as <strong class="font-medium text-gray-700">{{ $video->sourceQualityLabel() }}</strong>.
            </p>
        </div>
    </div>

    @if ($video->renditions->isEmpty())
        <div class="mt-4 rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
            No extra qualities yet — learners get the original file only.
        </div>
    @else
        <ul class="mt-4 divide-y divide-gray-100 rounded-lg border border-gray-200">
            @foreach ($video->renditions as $rendition)
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">
                            <x-mbui.badge appearance="info">{{ $rendition->quality }}</x-mbui.badge>
                            <span class="ml-2 text-gray-600">{{ $rendition->sizeLabel() }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500">Added {{ $rendition->created_at?->format('d M Y, H:i') }}</p>
                    </div>
                    <x-learning.confirm-delete :action="route('studio.videos.renditions.destroy', [$video, $rendition])"
                        :title="'Remove the '.$rendition->quality.' quality?'"
                        :impact="['The '.$rendition->quality.' file ('.$rendition->sizeLabel().') is deleted', 'Learners watching in this quality switch to another one']"
                        :button-label="'Remove '.$rendition->quality" />
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('studio.videos.renditions.store', $video) }}" class="mt-6 space-y-4 rounded-lg bg-gray-50 p-4">
        @csrf
        <input type="hidden" name="_panel" value="rendition">
        <h3 class="text-sm font-semibold text-gray-900">Add a quality</h3>
        <div class="max-w-xs">
            <label for="rendition-quality" class="mbui-label">Quality <span class="text-red-600">*</span></label>
            @php($taken = $video->renditions->pluck('quality')->all())
            <select id="rendition-quality" name="quality" required class="mbui-input mt-1 w-full">
                <option value="">Choose…</option>
                @foreach ($qualities as $quality)
                    <option value="{{ $quality }}" @selected(old('quality') === $quality)>
                        {{ $quality }}{{ in_array($quality, $taken, true) ? ' (replaces the current file)' : '' }}
                    </option>
                @endforeach
            </select>
            @error('quality')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        @include('studio.videos.partials.uploader', [
            'config' => $renditionUploader,
            'label' => 'Encoded file',
            'inputId' => 'rendition-file',
            'panel' => 'rendition',
            'help' => 'Encode the copy yourself (e.g. with HandBrake) at the chosen height; it is stored as uploaded.',
        ])

        <div class="flex justify-end">
            <x-mbui.button type="submit" class="disabled:cursor-not-allowed disabled:opacity-50">Add quality</x-mbui.button>
        </div>
    </form>
</x-mbui.card>
