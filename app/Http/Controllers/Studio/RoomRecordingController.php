<?php

namespace App\Http\Controllers\Studio;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningRoom;
use App\Models\LearningRoomRecording;
use App\Models\LearningVideo;
use App\Services\Learning\LearningDeletionService;
use App\Services\Learning\LearningStorage;
use App\Services\Learning\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Session recordings: upload, share toggle, publish as a lesson, delete.
 */
class RoomRecordingController extends Controller
{
    public function store(Request $request, LearningRoom $room, LearningStorage $storage): RedirectResponse
    {
        $this->authorize('manage', $room);
        $user = $request->user();

        $data = $request->validate([
            'upload_token' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{40}\z/'],
            'learning_room_session_id' => ['nullable', 'integer',
                Rule::exists('learning_room_sessions', 'id')->where('learning_room_id', $room->id)],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'is_shared' => ['nullable', 'boolean'],
        ], [
            'upload_token.required' => 'Upload the recording file first.',
            'upload_token.regex' => 'The upload is not valid. Please upload the file again.',
            'learning_room_session_id.exists' => 'Pick a session of this room.',
        ]);

        $file = $storage->adoptUpload($user, $data['upload_token'], 'recording', 'learning/recordings');

        $recording = $room->recordings()->create([
            'learning_room_session_id' => $data['learning_room_session_id'] ?? null,
            'source' => 'upload',
            'status' => 'ready',
            'disk' => $file['disk'],
            'path' => $file['path'],
            'original_name' => $file['original_name'],
            'mime' => $file['mime'],
            'size_bytes' => $file['size_bytes'],
            'duration_seconds' => isset($data['duration_seconds']) && (int) $data['duration_seconds'] > 0 ? (int) $data['duration_seconds'] : null,
            'is_shared' => $request->boolean('is_shared'),
            'uploaded_by' => $user->id,
        ]);

        ActivityLog::log('learning_room_recording_uploaded', 'LearningRoomRecording', $recording->id, [
            'room_id' => $room->id,
            'session_id' => $recording->learning_room_session_id,
            'size_bytes' => $recording->size_bytes,
        ]);

        return redirect()->to(route('studio.rooms.show', $room).'#recordings')
            ->with('success', 'Recording uploaded.'.($recording->is_shared ? ' Learners who can see the room can watch it.' : ' Only you and room staff can watch it until you share it.'));
    }

    public function update(Request $request, LearningRoom $room, LearningRoomRecording $recording): JsonResponse|RedirectResponse
    {
        $this->authorize('manage', $room);

        $data = $request->validate([
            'is_shared' => ['required', 'boolean'],
        ]);

        $recording->forceFill(['is_shared' => (bool) $data['is_shared']])->save();

        ActivityLog::log('learning_room_recording_updated', 'LearningRoomRecording', $recording->id, [
            'room_id' => $room->id,
            'is_shared' => $recording->is_shared,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $recording->id, 'is_shared' => $recording->is_shared]);
        }

        return redirect()->to(route('studio.rooms.show', $room).'#recordings')
            ->with('success', $recording->is_shared ? 'Recording shared with the room’s learners.' : 'Recording is now private to room staff.');
    }

    public function publish(Request $request, LearningRoom $room, LearningRoomRecording $recording, VideoService $videos): RedirectResponse
    {
        $this->authorize('manage', $room);
        $this->authorize('create', LearningVideo::class);
        $user = $request->user();

        try {
            $video = $videos->createFromRecording($recording, $user);
        } catch (ValidationException $e) {
            return redirect()->to(route('studio.rooms.show', $room).'#recordings')
                ->with('error', collect($e->errors())->flatten()->first() ?? 'The recording could not be published.');
        }

        if ($user->can('update', $video)) {
            return redirect()->route('studio.videos.edit', $video)
                ->with('success', 'Draft lesson created from the recording. Review the details, then publish it.');
        }

        return redirect()->to(route('studio.rooms.show', $room).'#recordings')
            ->with('success', 'Draft lesson “'.$video->title.'” created for the room’s host to review.');
    }

    public function destroy(Request $request, LearningRoom $room, LearningRoomRecording $recording, LearningDeletionService $deletions): RedirectResponse
    {
        $this->authorize('manage', $room);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'Give a reason for deleting this recording.',
            'reason.min' => 'The reason must be at least 3 characters.',
        ]);

        try {
            $deletions->deleteRecording($recording, trim($data['reason']));
        } catch (LearningDeletionBlocked $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->to(route('studio.rooms.show', $room).'#recordings')->with('success', 'Recording deleted.');
    }
}
