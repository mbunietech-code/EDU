<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores payment-proof screenshots as small WebP files. A proof only needs
 * to be legible, so the longest side is capped and quality lowered — typically
 * cutting 1–5 MB phone screenshots down to a few tens of KB. If anything about
 * the conversion isn't possible the original file is stored unchanged, so an
 * upload never fails because of compression.
 */
class PaymentProofImageService
{
    public const DIRECTORY = 'payment-proofs';
    private const MAX_SIDE = 1200;
    private const QUALITY = 65;
    private const MAX_PIXELS = 40_000_000;

    /**
     * @return string path on the private disk
     */
    public function store(UploadedFile $file): string
    {
        $original = (string) file_get_contents($file->getRealPath());

        $webp = $this->toWebp($original);

        if ($webp !== null && strlen($webp) < strlen($original)) {
            $path = self::DIRECTORY.'/'.Str::random(40).'.webp';
            Storage::disk('private')->put($path, $webp);

            return $path;
        }

        return $file->store(self::DIRECTORY, 'private');
    }

    /**
     * @return string|null WebP bytes, or null when conversion isn't possible
     */
    public function toWebp(string $bytes): ?string
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $info = @getimagesizefromstring($bytes);
            if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS) {
                return null;
            }

            $image = @imagecreatefromstring($bytes);
            if ($image === false) {
                return null;
            }

            $image = $this->applyExifOrientation($image, $bytes, $info['mime'] ?? '');
            $image = $this->downscale($image);

            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);

            ob_start();
            $ok = imagewebp($image, null, self::QUALITY);
            $data = (string) ob_get_clean();
            imagedestroy($image);

            return $ok && $data !== '' ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function downscale(\GdImage $image): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $longest = max($w, $h);

        if ($longest <= self::MAX_SIDE) {
            return $image;
        }

        $scale = self::MAX_SIDE / $longest;
        $resized = imagescale($image, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        if ($resized === false) {
            return $image;
        }
        imagedestroy($image);

        return $resized;
    }

    /**
     * Phone photos are often stored sideways with an EXIF rotation flag that
     * GD ignores; without this, converted proofs would appear rotated.
     */
    private function applyExifOrientation(\GdImage $image, string $bytes, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $angle = match ($exif['Orientation'] ?? 1) {
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
