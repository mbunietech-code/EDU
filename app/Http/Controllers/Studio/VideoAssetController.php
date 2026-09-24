<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\LearningVideo;
use App\Models\LearningVideoRendition;
use App\Models\LearningVideoResource;
use App\Services\Learning\VideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Extra qualities (renditions) and downloadable resources of a lesson.
 *
 * Nested models are scope-bound to the lesson by the routes, and every
 * action requires the "update" ability on the lesson.
 */
class VideoAssetController extends Controller
{
    public function __construct(protected VideoService $videos)
    {
    }

    public function storeRendition(Request $request, LearningVideo $video): RedirectResponse
    {
        $this->authorize('update', $video);

        $data = $request->validate([
            'quality' => ['required', Rule::in(array_keys(LearningVideo::QUALITIES))],
            'upload_token' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{40}\z/'],
        ], [
            'quality.required' => 'Choose the quality of this file.',
            'quality.in' => 'Choose one of: '.implode(', ', array_keys(LearningVideo::QUALITIES)).'.',
            'upload_token.required' => 'Upload the file for this quality first.',
            'upload_token.regex' => 'The upload is not valid. Please upload the file again.',
        ]);

        $replacing = $video->renditions()->where('quality', $data['quality'])->exists();

        $this->videos->addRendition($video, $data['upload_token'], $data['quality'], $request->user());

        return $this->toSection($video, 'renditions')->with('success', $replacing
            ? "The {$data['quality']} file was replaced."
            : "{$data['quality']} quality added.");
    }

    public function destroyRendition(Request $request, LearningVideo $video, LearningVideoRendition $rendition): RedirectResponse
    {
        $this->authorize('update', $video);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->videos->removeRendition($rendition, $request->user());

        return $this->toSection($video, 'renditions')->with('success', "{$rendition->quality} quality removed.");
    }

    public function storeResource(Request $request, LearningVideo $video): RedirectResponse
    {
        $this->authorize('update', $video);

        $extensions = array_values(config('learning.resource_extensions', []));
        $maxKb = max(1, (int) config('learning.max_resource_mb', 50)) * 1024;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(LearningVideoResource::TYPES)],
            'url' => ['nullable', 'required_if:type,link', 'string', 'max:500', 'url:http,https'],
            'file' => ['nullable', 'required_if:type,file', 'file', 'max:'.$maxKb, 'extensions:'.implode(',', $extensions)],
        ], [
            'url.required_if' => 'Enter the link address.',
            'url.url' => 'Enter a valid http(s) link.',
            'file.required_if' => 'Choose a file to attach.',
            'file.max' => 'Resources can be at most '.config('learning.max_resource_mb', 50).' MB.',
            'file.extensions' => 'Resources must be one of: '.strtoupper(implode(', ', $extensions)).'.',
        ]);

        $this->videos->addResource($video, [
            'title' => $data['title'],
            'type' => $data['type'],
            'url' => $data['type'] === 'link' ? ($data['url'] ?? null) : null,
            'file' => $data['type'] === 'file' ? $request->file('file') : null,
        ], $request->user());

        return $this->toSection($video, 'resources')->with('success', 'Resource “'.$data['title'].'” added.');
    }

    public function destroyResource(Request $request, LearningVideo $video, LearningVideoResource $resource): RedirectResponse
    {
        $this->authorize('update', $video);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->videos->removeResource($resource, $request->user());

        return $this->toSection($video, 'resources')->with('success', 'Resource “'.$resource->title.'” removed.');
    }

    private function toSection(LearningVideo $video, string $anchor): RedirectResponse
    {
        return redirect()->to(route('studio.videos.edit', $video).'#'.$anchor);
    }
}
