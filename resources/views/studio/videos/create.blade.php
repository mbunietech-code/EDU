<x-layouts.app title="Add video lesson" header="Teaching Studio">

    {{-- The admin layout has no [x-cloak] rule; keep hidden Alpine parts hidden until Alpine starts. --}}
    <style>[x-cloak]{display:none !important;}</style>

    <div class="mbui-page-header">
        <div>
            <nav class="mb-1 text-sm text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('studio.videos.index') }}" class="mbui-anchor">Video lessons</a>
                <span aria-hidden="true">/</span>
                <span>New</span>
            </nav>
            <h1 class="mbui-title">Add a video lesson</h1>
            <p class="mt-1 text-sm text-gray-500">Upload the video, describe it and choose where it belongs. You can save a draft and publish later.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('studio.videos.index')" variant="secondary">Cancel</x-mbui.btn-link>
        </div>
    </div>

    <form method="POST" action="{{ route('studio.videos.store') }}" enctype="multipart/form-data"
        x-data="learnVideoForm(@js($formConfig))" class="mt-6">
        @csrf

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="min-w-0 space-y-6 lg:col-span-2">
                <x-mbui.card>
                    <h2 class="mbui-section-label">Video</h2>
                    <div class="mt-3">
                        @include('studio.videos.partials.uploader', [
                            'config' => $uploader,
                            'label' => 'Video file',
                            'inputId' => 'video-file',
                            'help' => 'We read the duration and capture a thumbnail in your browser — nothing is converted on the server.',
                        ])
                    </div>
                </x-mbui.card>

                <x-mbui.card>
                    <h2 class="mbui-section-label">Details</h2>
                    <div class="mt-3">
                        @include('studio.videos.partials._form')
                    </div>
                </x-mbui.card>
            </div>

            <div class="min-w-0 space-y-6">
                <x-mbui.card>
                    <h2 class="mbui-section-label">Settings</h2>
                    <div class="mt-3">
                        @include('studio.videos.partials._settings', ['autoThumbnailHint' => true])
                    </div>
                </x-mbui.card>

                <x-mbui.card>
                    <h2 class="mbui-section-label">Publish</h2>
                    <label class="mt-3 flex items-start gap-3">
                        <input type="checkbox" name="notify" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            @checked(session()->hasOldInput() ? old('notify') : true)>
                        <span class="text-sm">
                            <span class="block font-medium text-gray-900">Notify eligible learners</span>
                            <span class="block text-xs text-gray-500">Sent once, when the lesson is first published.</span>
                        </span>
                    </label>
                    @error('status')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror

                    {{-- Draft comes first in the DOM so pressing Enter in a field saves a draft; publish is shown on top. --}}
                    <div class="mt-5 flex flex-col-reverse gap-2">
                        <x-mbui.button type="submit" name="action" value="draft" variant="secondary"
                            class="w-full disabled:cursor-not-allowed disabled:opacity-50">
                            Save draft
                        </x-mbui.button>
                        <x-mbui.button type="submit" name="action" value="publish" data-requires-upload
                            class="w-full disabled:cursor-not-allowed disabled:opacity-50">
                            Publish now
                        </x-mbui.button>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">Publishing needs a finished upload. Drafts are only visible to you and learning staff.</p>
                </x-mbui.card>
            </div>
        </div>
    </form>
</x-layouts.app>
