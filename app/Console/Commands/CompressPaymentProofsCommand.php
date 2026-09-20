<?php

namespace App\Console\Commands;

use App\Models\PaymentProof;
use App\Services\PaymentProofImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CompressPaymentProofsCommand extends Command
{
    protected $signature = 'proofs:compress {--delete-originals : Remove the old file after its compressed copy is saved}';

    protected $description = 'Convert existing payment-proof images to small WebP files and point the records at them.';

    public function handle(PaymentProofImageService $images): int
    {
        $disk = Storage::disk('private');
        $saved = 0;
        $converted = 0;

        PaymentProof::where('image_path', 'not like', '%.webp')->each(function (PaymentProof $proof) use ($images, $disk, &$saved, &$converted) {
            if (! $disk->exists($proof->image_path)) {
                return;
            }

            $original = $disk->get($proof->image_path);
            $webp = $images->toWebp($original);

            if ($webp === null || strlen($webp) >= strlen($original)) {
                return;
            }

            $newPath = PaymentProofImageService::DIRECTORY.'/'.pathinfo($proof->image_path, PATHINFO_FILENAME).'.webp';
            $disk->put($newPath, $webp);

            $oldPath = $proof->image_path;
            $proof->update(['image_path' => $newPath]);

            if ($this->option('delete-originals')) {
                $disk->delete($oldPath);
            }

            $saved += strlen($original) - strlen($webp);
            $converted++;
        });

        $this->info("Converted {$converted} image(s), saving ".round($saved / 1048576, 2).' MB'
            .($this->option('delete-originals') ? '.' : ' (originals kept — re-run with --delete-originals to free the space).'));

        return self::SUCCESS;
    }
}
