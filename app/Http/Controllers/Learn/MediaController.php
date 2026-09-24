<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\LearningRoomRecording;
use App\Models\LearningVideo;
use App\Models\LearningVideoRendition;
use App\Models\LearningVideoResource;
use App\Models\User;
use App\Services\Learning\LearningStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Authorised streaming of lesson media, resources and room recordings.
 *
 * Files live on the private learning disk; LearningStorage::streamResponse()
 * serves them with Range / 206 support (local disk) or a short-lived
 * temporary URL (cloud disk). Nested models are resolved with scopeBindings(),
 * and the ownership is re-checked here as a second line of defence.
 */
class MediaController extends Controller
{
    public function __construct(protected LearningStorage $storage)
    {
    }

    public function stream(Request $request, LearningVideo $video, ?LearningVideoRendition $rendition = null)
    {
        $this->authorize('view', $video);

        return $this->videoResponse($request->user(), $video, $rendition);
    }

    public function resource(Request $request, LearningVideo $video, LearningVideoResource $resource)
    {
        $this->authorize('view', $video);

        abort_unless($video->isPublished() || Gate::allows('update', $video), 403);
        abort_unless((int) $resource->learning_video_id === (int) $video->id, 404);
        abort_unless($resource->isFile() && $resource->disk && $resource->path, 404);

        $extension = strtolower(pathinfo((string) ($resource->original_name ?: $resource->path), PATHINFO_EXTENSION));
        $name = (Str::slug((string) $resource->title) ?: 'resource').($extension !== '' ? '.'.$extension : '');

        return $this->storage->streamResponse(
            $resource->disk,
            $resource->path,
            $resource->mime ?: 'application/octet-stream',
            $name,
        );
    }

    public function recording(Request $request, LearningRoom $room, LearningRoomRecording $recording)
    {
        $this->authorize('view', $room);

        abort_unless((int) $recording->learning_room_id === (int) $room->id, 404);
        abort_unless($recording->isReady() && $recording->disk && $recording->path, 404);
        abort_unless($recording->is_shared || $room->isManageableBy($request->user()), 403);

        return $this->storage->streamResponse($recording->disk, $recording->path, $recording->mime ?: 'video/mp4');
    }

    /**
     * Session-less stream for API / mobile players. The route's "signed"
     * middleware has already verified the URL (incl. ?u=); the user must
     * still exist, be active and be allowed to watch the lesson right now.
     */
    public function signedStream(Request $request, LearningVideo $video, ?LearningVideoRendition $rendition = null)
    {
        $userId = filter_var($request->query('u'), FILTER_VALIDATE_INT);
        $user = $userId ? User::query()->find($userId) : null;

        abort_unless($user && $user->isActive(), 403);
        abort_unless(Gate::forUser($user)->allows('view', $video), 403);

        return $this->videoResponse($user, $video, $rendition);
    }

    // --- Internals -----------------------------------------------------
    private function videoResponse(User $user, LearningVideo $video, ?LearningVideoRendition $rendition)
    {
        // Drafts only play for the people who can edit them (Gate::before covers super admins).
        abort_unless($video->isPublished() || Gate::forUser($user)->allows('update', $video), 403);
        abort_unless($video->hasFile(), 404);

        if ($rendition !== null) {
            abort_unless((int) $rendition->learning_video_id === (int) $video->id, 404);

            return $this->storage->streamResponse($rendition->disk ?: $this->storage->disk(), $rendition->path, $rendition->mime ?: 'video/mp4');
        }

        return $this->storage->streamResponse($video->disk ?: $this->storage->disk(), $video->path, $video->mime ?: 'video/mp4');
    }
}
