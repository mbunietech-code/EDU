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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pulls a JaaS cloud recording (its 24-hour pre-authenticated link in
 * external_url) into our own storage: streams it with
 * Http::withOptions(['sink' => …]) to a local temp file, moves it under
 * learning/recordings/ on LearningStorage::disk(), marks the recording ready
 * (size, mime video/mp4) and notifies the host (RecordingReady). On failure
 * the recording is marked failed with the error.
 */
class DownloadRoomRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(public int $recordingId)
    {
    }

    /** Seconds to wait before the 2nd and 3rd attempt (8x8 storage hiccups are usually brief). */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(LearningStorage $storage, LearningNotifier $notifier): void
    {
        $recording = LearningRoomRecording::query()->find($this->recordingId);

        // Deleted meanwhile, or already imported by an earlier attempt.
        if (! $recording || ($recording->isReady() && $recording->path)) {
            return;
        }

        // Nothing a retry could fix: record the failure and drop the job.
        if (! $recording->external_url) {
            $this->failed(new \RuntimeException('The recording has no download link.'));
            $this->delete();

            return;
        }

        $temp = tempnam(sys_get_temp_dir(), 'jaas-rec-');

        if ($temp === false) {
            throw new \RuntimeException('Could not create a temporary file for the recording download.');
        }

        try {
            $response = Http::withOptions(['sink' => $temp])
                ->connectTimeout(30)
                ->timeout($this->timeout - 60)
                ->get($recording->external_url);

            if ($response->failed()) {
                throw new \RuntimeException('The recording download failed with HTTP '.$response->status().'.');
            }

            clearstatcache(true, $temp);
            $size = (int) filesize($temp);

            if ($size === 0) {
                throw new \RuntimeException('The downloaded recording is empty.');
            }

            $disk = $storage->disk();
            $path = Storage::disk($disk)->putFileAs(
                'learning/recordings/'.$recording->learning_room_id,
                new File($temp),
                Str::random(40).'.mp4'
            );

            if ($path === false) {
                throw new \RuntimeException('The recording could not be written to the "'.$disk.'" disk.');
            }
        } finally {
            @unlink($temp);
        }

        // The recording row may have been deleted while we were downloading.
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
                'error' => Str::limit($e?->getMessage() ?: 'The recording could not be downloaded.', 1000),
            ]);
    }
}
