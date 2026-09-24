<?php

namespace App\Services\Learning;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resumable chunked uploads for large lesson videos, renditions and room
 * recordings — shared hosting caps a single request far below a lecture
 * video. Each upload lives on Storage::disk('local') under
 * learning-uploads/{token}/ as meta.json + file.part until it is adopted by
 * LearningStorage::adoptUpload() or cleaned up.
 *
 * Validation errors use these keys: purpose / filename / size (init),
 * upload_token (unknown, foreign, finished or incomplete upload), index
 * (out-of-order chunk), chunk (bad or oversized chunk) and file (the
 * assembled file is not an allowed media type).
 */
class ChunkedUploadService
{
    public const ROOT = 'learning-uploads';

    private const META = 'meta.json';

    private const PART = 'file.part';

    private const LOCK = 'upload.lock';

    private const MB = 1048576;

    /**
     * Client limits: ['chunk_bytes' => int (min(config chunk_mb, 90% of the
     * smaller of upload_max_filesize / post_max_size)), 'purposes' => [
     * 'video' => ['max_bytes','extensions','mimes'], 'rendition' => …, 'recording' => …]].
     *
     * @return array<string,mixed>
     */
    public function limits(): array
    {
        $chunk = max(1, (int) config('learning.chunk_mb', 5)) * self::MB;

        $server = array_filter([
            $this->iniBytes((string) ini_get('upload_max_filesize')),
            $this->iniBytes((string) ini_get('post_max_size')),
        ], fn (int $bytes) => $bytes > 0); // 0 = no limit

        if ($server !== []) {
            $chunk = min($chunk, (int) floor(min($server) * 0.9));
        }

        $media = [
            'max_bytes' => max(1, (int) config('learning.max_video_mb', 4096)) * self::MB,
            'extensions' => array_values(config('learning.video_extensions', [])),
            'mimes' => array_values(config('learning.video_mimes', [])),
        ];

        return [
            'chunk_bytes' => max(1, $chunk),
            'purposes' => [
                'video' => $media,
                'rendition' => $media,
                'recording' => $media,
            ],
        ];
    }

    /**
     * Start an upload.
     *
     * @return string token matching [A-Za-z0-9]{40}
     *
     * @throws ValidationException on an unknown purpose, a disallowed extension or a bad size
     */
    public function init(User $user, string $purpose, string $filename, int $size): string
    {
        $rules = $this->purposeRules($purpose);

        $name = $this->cleanFilename($filename);
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));

        if ($name === '' || ! in_array($extension, $rules['extensions'], true)) {
            throw ValidationException::withMessages([
                'filename' => 'Only '.Str::upper(implode(', ', $rules['extensions'])).' files can be uploaded.',
            ]);
        }

        if ($size < 1 || $size > $rules['max_bytes']) {
            throw ValidationException::withMessages([
                'size' => 'The file must be between 1 byte and '.intdiv($rules['max_bytes'], self::MB).' MB.',
            ]);
        }

        $disk = $this->disk();
        do {
            $token = Str::random(40);
        } while ($disk->exists($this->dir($token)));

        $disk->makeDirectory($this->dir($token));
        $disk->put($this->dir($token).'/'.self::PART, '');

        $this->writeMeta($token, [
            'token' => $token,
            'user_id' => $user->id,
            'purpose' => $purpose,
            'original_name' => $name,
            'size' => $size,
            'received_bytes' => 0,
            'next_index' => 0,
            'status' => 'uploading',
            'mime' => null,
            'created_at' => now()->getTimestamp(),
            'updated_at' => now()->getTimestamp(),
        ]);

        return $token;
    }

    /**
     * Append chunk $index. Retry-safe: an index below the next expected one
     * is acknowledged without writing. Appends are serialised with flock() so
     * a client retry racing the original request cannot write a chunk twice.
     *
     * @return array{received_bytes:int,next_index:int}
     */
    public function appendChunk(User $user, string $token, int $index, UploadedFile $chunk): array
    {
        $this->ownedMeta($user, $token);

        if (! $chunk->isValid() || ($bytes = (int) $chunk->getSize()) < 1) {
            throw ValidationException::withMessages(['chunk' => 'The chunk did not arrive intact. Please retry.']);
        }

        if ($bytes > $this->limits()['chunk_bytes']) {
            throw ValidationException::withMessages(['chunk' => 'The chunk is larger than the server accepts.']);
        }

        return $this->withLock($token, function () use ($token, $index, $chunk, $bytes) {
            // Re-read under the lock: this is the authoritative state.
            $meta = $this->readMeta($token);

            if ($meta === null || $meta['status'] !== 'uploading') {
                throw ValidationException::withMessages(['upload_token' => 'This upload is no longer accepting data.']);
            }

            $state = fn (array $m) => ['received_bytes' => (int) $m['received_bytes'], 'next_index' => (int) $m['next_index']];

            if ($index < $meta['next_index']) {
                return $state($meta); // a retry of a chunk we already have
            }

            if ($index > $meta['next_index']) {
                throw ValidationException::withMessages([
                    'index' => "Chunk {$index} arrived out of order; expected chunk {$meta['next_index']}.",
                ]);
            }

            if ($meta['received_bytes'] + $bytes > $meta['size']) {
                throw ValidationException::withMessages(['chunk' => 'The upload is larger than the size declared when it started.']);
            }

            $out = @fopen($this->partPath($token), 'r+b');
            $in = @fopen((string) $chunk->getRealPath(), 'rb');

            try {
                if ($out === false || $in === false) {
                    throw ValidationException::withMessages(['chunk' => 'The chunk could not be saved. Please retry.']);
                }

                // Drop any tail left by a request that died mid-write before meta was saved.
                ftruncate($out, (int) $meta['received_bytes']);
                fseek($out, 0, SEEK_END);
                $written = stream_copy_to_stream($in, $out);
                fflush($out);

                if ($written !== $bytes) {
                    ftruncate($out, (int) $meta['received_bytes']);
                    throw ValidationException::withMessages(['chunk' => 'The chunk could not be saved. Please retry.']);
                }
            } finally {
                foreach ([$in, $out] as $stream) {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }

            $meta['received_bytes'] += $bytes;
            $meta['next_index']++;
            $meta['updated_at'] = now()->getTimestamp();
            $this->writeMeta($token, $meta);

            return $state($meta);
        });
    }

    /**
     * Finish an upload: received bytes must equal the declared size and the
     * finfo-detected MIME must be allowed for the purpose. Calling it again
     * on a finished upload returns the same summary. A file whose content is
     * not an allowed type is discarded.
     *
     * @return array{token:string,size:int,mime:string,original_name:string}
     */
    public function complete(User $user, string $token): array
    {
        $this->ownedMeta($user, $token);

        $result = $this->withLock($token, function () use ($token) {
            $meta = $this->readMeta($token);

            if ($meta === null) {
                throw ValidationException::withMessages(['upload_token' => 'This upload no longer exists. Please upload the file again.']);
            }

            if ($meta['status'] !== 'complete') {
                $absolute = $this->partPath($token);
                clearstatcache(true, $absolute);

                if ((int) $meta['received_bytes'] !== (int) $meta['size'] || @filesize($absolute) !== (int) $meta['size']) {
                    throw ValidationException::withMessages([
                        'upload_token' => "The upload is incomplete ({$meta['received_bytes']} of {$meta['size']} bytes received).",
                    ]);
                }

                $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($absolute);
                $allowed = $this->purposeRules($meta['purpose'])['mimes'];

                if (! in_array($mime, $allowed, true)) {
                    return ['rejected' => $mime !== '' ? $mime : 'unknown'];
                }

                $meta['status'] = 'complete';
                $meta['mime'] = $mime;
                $meta['updated_at'] = now()->getTimestamp();
                $this->writeMeta($token, $meta);
            }

            return [
                'token' => $token,
                'size' => (int) $meta['size'],
                'mime' => (string) $meta['mime'],
                'original_name' => (string) $meta['original_name'],
            ];
        });

        if (isset($result['rejected'])) {
            // Discarded only after the lock is released (Windows cannot delete an open file).
            $this->forget($token);

            throw ValidationException::withMessages([
                'file' => "The file is not a supported video (detected type: {$result['rejected']}).",
            ]);
        }

        return $result;
    }

    /** Cancel an upload owned by $user and delete its temp files (already gone = nothing to do). */
    public function abort(User $user, string $token): void
    {
        $this->assertTokenFormat($token);

        if ($this->readMeta($token) === null) {
            $this->forget($token);

            return;
        }

        $this->ownedMeta($user, $token);
        $this->forget($token);
    }

    /**
     * Locate a completed upload owned by $user for $purpose.
     *
     * @return array{absolute_path:string,original_name:string,mime:string,size:int}
     *
     * @throws ValidationException (key 'upload_token')
     */
    public function resolveCompleted(User $user, string $token, string $purpose): array
    {
        $meta = $this->ownedMeta($user, $token);
        $absolute = $this->partPath($token);
        clearstatcache(true, $absolute);

        if ($meta['purpose'] !== $purpose
            || $meta['status'] !== 'complete'
            || ! is_file($absolute)
            || filesize($absolute) !== (int) $meta['size']) {
            throw ValidationException::withMessages(['upload_token' => 'The uploaded file is not ready. Please upload it again.']);
        }

        return [
            'absolute_path' => $absolute,
            'original_name' => (string) $meta['original_name'],
            'mime' => (string) $meta['mime'],
            'size' => (int) $meta['size'],
        ];
    }

    /** Drop an upload's temp directory (after it has been adopted). Malformed tokens are ignored. */
    public function forget(string $token): void
    {
        if ($this->validToken($token)) {
            $this->disk()->deleteDirectory($this->dir($token));
        }
    }

    /**
     * Delete abandoned uploads last touched more than $olderThanHours ago.
     * Stray entries under the upload root that are not uploads are removed too.
     *
     * @return int number of uploads removed
     */
    public function cleanup(int $olderThanHours = 24): int
    {
        $disk = $this->disk();
        $cutoff = now()->subHours(max(1, $olderThanHours))->getTimestamp();
        $removed = 0;

        foreach ($disk->directories(self::ROOT) as $dir) {
            $token = basename($dir);

            if (! $this->validToken($token)) {
                if ($disk->lastModified($dir) < $cutoff) {
                    $disk->deleteDirectory($dir);
                }

                continue;
            }

            $meta = $this->readMeta($token);
            $touched = $meta['updated_at'] ?? null;

            if ($touched === null) {
                $touched = $disk->exists($dir.'/'.self::PART)
                    ? $disk->lastModified($dir.'/'.self::PART)
                    : $disk->lastModified($dir);
            }

            if ((int) $touched < $cutoff) {
                $disk->deleteDirectory($dir);
                $removed++;
            }
        }

        return $removed;
    }

    // --- Internals -----------------------------------------------------
    private function disk(): Filesystem
    {
        return Storage::disk(config('learning.upload_tmp_disk', 'local'));
    }

    private function dir(string $token): string
    {
        return self::ROOT.'/'.$token;
    }

    private function partPath(string $token): string
    {
        return $this->disk()->path($this->dir($token).'/'.self::PART);
    }

    /** @return array{max_bytes:int,extensions:list<string>,mimes:list<string>} */
    private function purposeRules(string $purpose): array
    {
        $rules = $this->limits()['purposes'][$purpose] ?? null;

        if ($rules === null) {
            throw ValidationException::withMessages(['purpose' => 'Unknown upload type.']);
        }

        return $rules;
    }

    /** Tokens are the only user input that reaches a path, so they must be exactly 40 alphanumerics. */
    private function validToken(string $token): bool
    {
        return preg_match('/\A[A-Za-z0-9]{40}\z/', $token) === 1;
    }

    private function assertTokenFormat(string $token): void
    {
        if (! $this->validToken($token)) {
            throw ValidationException::withMessages(['upload_token' => 'Invalid upload.']);
        }
    }

    /** @return array<string,mixed> */
    private function ownedMeta(User $user, string $token): array
    {
        $this->assertTokenFormat($token);
        $meta = $this->readMeta($token);

        if ($meta === null || (int) $meta['user_id'] !== (int) $user->id) {
            throw ValidationException::withMessages(['upload_token' => 'This upload was not found. Please upload the file again.']);
        }

        return $meta;
    }

    /** @return array<string,mixed>|null */
    private function readMeta(string $token): ?array
    {
        $path = $this->dir($token).'/'.self::META;
        $raw = $this->disk()->exists($path) ? $this->disk()->get($path) : null;
        $meta = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($meta) && isset($meta['user_id'], $meta['purpose'], $meta['size'], $meta['status'])
            ? $meta
            : null;
    }

    /** Write-then-rename so a concurrent reader never sees half a file. */
    private function writeMeta(string $token, array $meta): void
    {
        $target = $this->disk()->path($this->dir($token).'/'.self::META);
        $tmp = $target.'.'.Str::random(8).'.tmp';

        if (file_put_contents($tmp, json_encode($meta, JSON_UNESCAPED_SLASHES)) === false || ! rename($tmp, $target)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not save the upload state.');
        }
    }

    /**
     * Run $callback holding an exclusive lock for the upload. The lock lives
     * on its own file: on Windows flock() is mandatory, so locking file.part
     * itself would stop finfo (a second handle) from reading it.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    private function withLock(string $token, callable $callback): mixed
    {
        $lockPath = $this->disk()->path($this->dir($token).'/'.self::LOCK);
        $lock = is_dir(dirname($lockPath)) ? @fopen($lockPath, 'c') : false;

        if ($lock === false) {
            throw ValidationException::withMessages(['upload_token' => 'This upload no longer exists. Please upload the file again.']);
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Could not lock the upload.');
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Keep only the base name, without control characters, at most 255 characters. */
    private function cleanFilename(string $filename): string
    {
        $name = basename(str_replace('\\', '/', $filename));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));

        return Str::limit($name, 255, '');
    }

    /** "64M" / "2G" / "512K" / "1048576" → bytes. */
    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $number = (int) $value;

        return match (Str::lower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * self::MB,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
