<?php

namespace Tests\Feature;

use App\Services\PaymentProofImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofImageTest extends TestCase
{
    public function test_large_image_is_stored_as_a_much_smaller_webp(): void
    {
        Storage::fake('private');
        $file = UploadedFile::fake()->image('proof.jpg', 3000, 2000);

        $path = app(PaymentProofImageService::class)->store($file);

        $this->assertStringEndsWith('.webp', $path);
        $this->assertStringStartsWith('payment-proofs/', $path);
        Storage::disk('private')->assertExists($path);

        $bytes = Storage::disk('private')->get($path);
        $info = getimagesizefromstring($bytes);
        $this->assertSame('image/webp', $info['mime']);
        $this->assertLessThanOrEqual(1200, max($info[0], $info[1]));
        $this->assertLessThan($file->getSize(), strlen($bytes));
    }

    public function test_unconvertible_file_falls_back_to_storing_the_original(): void
    {
        Storage::fake('private');
        $file = UploadedFile::fake()->createWithContent('proof.png', 'not really an image');

        $path = app(PaymentProofImageService::class)->store($file);

        $this->assertStringStartsWith('payment-proofs/', $path);
        $this->assertStringEndsNotWith('.webp', $path);
        Storage::disk('private')->assertExists($path);
    }
}
