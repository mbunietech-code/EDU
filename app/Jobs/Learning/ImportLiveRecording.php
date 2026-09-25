<?php

namespace App\Jobs\Learning;

use App\Models\LearningRoomRecording;
use App\Services\Learning\LearningNotifier;
use App\Services\Learning\LearningStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports a finished server recording (LiveKit Egress on our own server)
 * into the learning disk: the MP4 Egress wrote to its output folder is read
 * from LIVE_RECORDING_IMPORT_DIR (the same folder as seen by Laravel), copied
 * under learning/recordings/{room}/, and the source file is removed. The
 * recording is marked ready and the host is notified (RecordingReady).
 *
 * external_url holds the file name Egress reported. Only a plain file name
 * inside the import folder is ever read — never an arbitrary path.
 */
class ImportLiveRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 1800;

    public function __construct(public int $recordingId)
    {
    }

    /** Egress may still be flushing the file when the webhook arrives. */
    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function handle(LearningStorage $storage, LearningNotifier $notifier): void
    {
        $recording = LearningRoomRecording::query()->find($this->recordingId);

        // Deleted meanwhile, or already imported by an earlier attempt.
        if (! $recording || ($recording->isReady() && $recording->path)) {
            return;
        }

        $source = $this->sourcePath((string) $recording->external_url);

        if ($source === null) {
            $this->failed(new \RuntimeException('LIVE_RECORDING_IMPORT_DIR is not set or the file name is invalid.'));
            $this->delete();

            return;
        }

        clearstatcache(true, $source);
        if (! is_file($source) || ! is_readable($source)) {
            throw new \RuntimeException('The recording file is not (yet) readable at '.$source.'.');
        }

        $size = (int) filesize($source);
        if ($size === 0) {
            throw new \RuntimeException('The recording file is empty.');
        }

        $disk = $storage->disk();
        $path = Storage::disk($disk)->putFileAs(
            'learning/recordings/'.$recording->learning_room_id,
            new File($source),
            Str::random(40).'.mp4'
        );

        if ($path === false) {
            throw new \RuntimeException('The recording could not be written to the "'.$disk.'" disk.');
        }

        // The recording row may have been deleted while we were copying.
        $recording = LearningRoomRecording::query()->find($this->recordingId);
        if (! $recording) {
            Storage::disk($disk)->delete($path);

            return;
        }

        $recording->forceFill([
            'status' => 'ready',
            'disk' => $disk,
            'path' => $path,
            'mime' => 'video/mp4',
            'size_bytes' => $size,
            'original_name' => $recording->original_name ?: 'recording-'.$recording->created_at?->format('Y-m-d-His').'.mp4',
            'error' => null,
        ])->save();

        @unlink($source);

        $notifier->notifyRecordingReady($recording);
    }

    /** All attempts used up (or fail() called): record why, so the studio can show it. */
    public function failed(?\Throwable $e): void
    {
        LearningRoomRecording::query()
            ->whereKey($this->recordingId)
            ->where('status', '!=', 'ready')
            ->update([
                'status' => 'failed',
                'error' => Str::limit($e?->getMessage() ?: 'The recording could not be imported.', 1000),
            ]);
    }

    /** Absolute path of the Egress file inside the import folder, or null. */
    private function sourcePath(string $reported): ?string
    {
        $dir = rtrim((string) config('learning.live.recording.import_dir'), '/\\');
        $name = basename(str_replace('\\', '/', $reported));

        if ($dir === '' || preg_match('/^[A-Za-z0-9._-]{1,200}\.(mp4|webm|ogg)$/i', $name) !== 1) {
            return null;
        }

        return $dir.DIRECTORY_SEPARATOR.$name;
    }
}
