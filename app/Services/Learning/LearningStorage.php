<?php

namespace App\Services\Learning;

use App\Models\LearningVideo;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

/**
 * Where learning files live. Lesson media, renditions, resources and
 * recordings sit on the private disk (config('learning.disk')) and are only
 * ever served through authorised/signed routes; thumbnails go to the public
 * disk (config('learning.public_disk')).
 */
class LearningStorage
{
    private const THUMB_MAX_SIDE = 1280;
    private const THUMB_QUALITY = 80;
    private const THUMB_MAX_PIXELS = 40_000_000;

    public function __construct(protected ChunkedUploadService $uploads)
    {
    }

    /** Private disk name for media, resources and recordings. */
    public function disk(): string
    {
        return (string) config('learning.disk', 'private');
    }

    /** Public disk name for thumbnails. */
    public function publicDisk(): string
    {
        return (string) config('learning.public_disk', 'public');
    }

    /**
     * Re-encode an uploaded image with GD (webp, or jpeg when webp is not
     * available), scaled to at most 1280 px, onto the public disk. Thumbnails
     * are public, so the file is always rebuilt from pixels: EXIF (GPS,
     * camera details) and anything smuggled after the image data are dropped.
     *
     * @return string the stored path on publicDisk()
     *
     * @throws ValidationException (key 'thumbnail') when the file is not a usable image
     */
    public function storeThumbnail(UploadedFile $file, string $dir = 'learning/thumbnails'): string
    {
        $fail = fn (string $message) => ValidationException::withMessages(['thumbnail' => $message]);

        if (! function_exists('imagecreatefromstring') || ! (function_exists('imagewebp') || function_exists('imagejpeg'))) {
            throw $fail('Thumbnails cannot be processed on this server (the GD image extension is missing).');
        }

        $bytes = (string) @file_get_contents((string) $file->getRealPath());
        $info = $bytes !== '' ? @getimagesizefromstring($bytes) : false;

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            throw $fail('The thumbnail must be a JPEG, PNG, WebP or GIF image.');
        }

        if ($info[0] * $info[1] > self::THUMB_MAX_PIXELS) {
            throw $fail('The thumbnail image is too large. Please use a smaller picture.');
        }

        try {
            $image = @imagecreatefromstring($bytes);
            if ($image === false) {
                throw $fail('The thumbnail image could not be read.');
            }

            $image = $this->applyExifOrientation($image, $bytes, (string) ($info['mime'] ?? ''));
            $image = $this->downscale($image);
            imagepalettetotruecolor($image);

            [$data, $extension] = function_exists('imagewebp')
                ? [$this->encodeWebp($image), 'webp']
                : [$this->encodeJpeg($image), 'jpg'];
            imagedestroy($image);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw $fail('The thumbnail image could not be processed.');
        }

        if ($data === '') {
            throw $fail('The thumbnail image could not be processed.');
        }

        $path = trim($dir, '/').'/'.Str::random(40).'.'.$extension;

        if (! Storage::disk($this->publicDisk())->put($path, $data)) {
            throw new \RuntimeException('Could not store the thumbnail.');
        }

        return $path;
    }

    /** Delete a stored file; never throws (failures are report()ed). */
    public function deleteFile(?string $disk, ?string $path): void
    {
        if (blank($disk) || blank($path)) {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Public URL of a thumbnail path, or null. */
    public function publicUrl(?string $path): ?string
    {
        return blank($path) ? null : Storage::disk($this->publicDisk())->url($path);
    }

    /**
     * Move a COMPLETED chunked upload into final storage on disk() under
     * $directory. On a local disk this is a rename (no second copy of a
     * multi-GB file); other drivers receive a stream.
     *
     * @return array{disk:string,path:string,original_name:string,mime:string,size_bytes:int}
     *
     * @throws ValidationException (key 'upload_token') when the token is
     *         invalid, owned by someone else, for another purpose, or not complete
     */
    public function adoptUpload(User $user, string $token, string $purpose, string $directory): array
    {
        $upload = $this->uploads->resolveCompleted($user, $token, $purpose);

        $extension = Str::lower(pathinfo($upload['original_name'], PATHINFO_EXTENSION));
        $extension = preg_match('/\A[a-z0-9]{1,10}\z/', $extension) ? $extension : 'bin';
        $path = trim($directory, '/').'/'.Str::random(40).'.'.$extension;

        $disk = $this->disk();
        $this->putFromLocalFile($disk, $path, $upload['absolute_path']);
        $this->uploads->forget($token);

        return [
            'disk' => $disk,
            'path' => $path,
            'original_name' => $upload['original_name'],
            'mime' => $upload['mime'],
            'size_bytes' => $upload['size'],
        ];
    }

    /**
     * Store a lesson resource (PDF, slides, …) on disk().
     *
     * @return array{disk:string,path:string,original_name:string,mime:string,size_bytes:int}
     *
     * @throws ValidationException (key 'file') for a disallowed type or size
     */
    public function storeResourceFile(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $allowed = config('learning.resource_extensions', []);
        $maxBytes = max(1, (int) config('learning.max_resource_mb', 50)) * 1048576;

        if (! $file->isValid() || ! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'Resources must be one of: '.Str::upper(implode(', ', $allowed)).'.',
            ]);
        }

        if ((int) $file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => 'Resources can be at most '.config('learning.max_resource_mb').' MB.',
            ]);
        }

        $disk = $this->disk();
        $path = $file->storeAs('learning/resources', Str::random(40).'.'.$extension, $disk);

        if ($path === false) {
            throw new \RuntimeException('Could not store the resource file.');
        }

        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))));

        return [
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($name !== '' ? $name : 'resource.'.$extension, 255, ''),
            'mime' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    /**
     * Serve a private file. Local driver → BinaryFileResponse (Range / 206
     * support) with Cache-Control "private, max-age=3600",
     * X-Content-Type-Options nosniff and Content-Disposition inline (or
     * attachment when $downloadName is given). Other drivers → redirect to a
     * temporaryUrl() valid for 3 hours. A missing file is a 404.
     */
    public function streamResponse(string $disk, string $path, string $mime, ?string $downloadName = null): Response
    {
        $filesystem = Storage::disk($disk);

        abort_unless($filesystem->exists($path), 404);

        $downloadName = $downloadName !== null
            ? trim(str_replace(['/', '\\', "\r", "\n"], '-', $downloadName))
            : null;

        if (! $this->isLocal($filesystem)) {
            return redirect()->away($filesystem->temporaryUrl($path, now()->addHours(3), array_filter([
                'ResponseContentType' => $mime,
                'ResponseContentDisposition' => $downloadName
                    ? ResponseHeaderBag::DISPOSITION_ATTACHMENT.'; filename="'.Str::ascii($downloadName).'"'
                    : null,
            ])));
        }

        $response = new BinaryFileResponse($filesystem->path($path), 200, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ], false, null, false, true); // no auto ETag: it would hash a multi-GB file on every request

        $response->setPrivate();
        $response->setMaxAge(3600);

        if ($downloadName) {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);
        } else {
            $response->headers->set('Content-Disposition', ResponseHeaderBag::DISPOSITION_INLINE);
        }

        return $response;
    }

    /**
     * Delete every file of a lesson: source, renditions, resources and
     * thumbnail. Uses the renditions/resources relations as loaded, so a
     * caller about to force-delete the rows loads them first.
     */
    public function purgeVideoFiles(LearningVideo $video): void
    {
        $this->deleteFile($video->disk, $video->path);

        foreach ($video->renditions as $rendition) {
            $this->deleteFile($rendition->disk, $rendition->path);
        }

        foreach ($video->resources as $resource) {
            if ($resource->isFile()) {
                $this->deleteFile($resource->disk, $resource->path);
            }
        }

        $this->deleteFile($this->publicDisk(), $video->thumbnail_path);
    }

    // --- Internals -----------------------------------------------------
    private function isLocal(Filesystem $filesystem): bool
    {
        return method_exists($filesystem, 'getAdapter') && $filesystem->getAdapter() instanceof LocalFilesystemAdapter;
    }

    /** Move (local) or stream (remote) a file from the upload temp area onto $disk. */
    private function putFromLocalFile(string $disk, string $path, string $absoluteSource): void
    {
        $filesystem = Storage::disk($disk);

        if ($this->isLocal($filesystem)) {
            $target = $filesystem->path($path);
            $dir = dirname($target);

            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new \RuntimeException('Could not create the storage directory.');
            }

            // rename() is instant on the same volume; across volumes fall back to a copy.
            if (@rename($absoluteSource, $target)) {
                return;
            }

            if (@copy($absoluteSource, $target)) {
                @unlink($absoluteSource);

                return;
            }

            throw new \RuntimeException('Could not move the uploaded file into storage.');
        }

        $stream = fopen($absoluteSource, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Could not read the uploaded file.');
        }

        try {
            if (! $filesystem->writeStream($path, $stream)) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function encodeWebp(\GdImage $image): string
    {
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagewebp($image, null, self::THUMB_QUALITY);
        $data = (string) ob_get_clean();

        return $ok ? $data : '';
    }

    /** JPEG has no alpha: flatten onto white so transparent PNGs don't turn black. */
    private function encodeJpeg(\GdImage $image): string
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, $w, $h);

        ob_start();
        $ok = imagejpeg($canvas, null, self::THUMB_QUALITY);
        $data = (string) ob_get_clean();
        imagedestroy($canvas);

        return $ok ? $data : '';
    }

    private function downscale(\GdImage $image): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $longest = max($w, $h);

        if ($longest <= self::THUMB_MAX_SIDE) {
            return $image;
        }

        $scale = self::THUMB_MAX_SIDE / $longest;
        $resized = imagescale($image, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        if ($resized === false) {
            return $image;
        }
        imagedestroy($image);

        return $resized;
    }

    /** Phone photos carry an EXIF rotation flag GD ignores; apply it before the EXIF is dropped. */
    private function applyExifOrientation(\GdImage $image, string $bytes, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $angle = match (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }
}
