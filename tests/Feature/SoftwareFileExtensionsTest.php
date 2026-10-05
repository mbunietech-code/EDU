<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SoftwareFileExtensionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('software.download_disk', 'private'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_tool_upload_accepts_dmg(): void
    {
        $this->postJson(route('admin.tool-files.store'), [
            'tool_file' => UploadedFile::fake()->create('EndNote2025.dmg', 100, 'application/x-apple-diskimage'),
        ])->assertOk()->assertJson(['filename' => 'EndNote2025.dmg']);
    }

    public function test_product_upload_accepts_dmg(): void
    {
        $this->postJson(route('admin.software-files.store'), [
            'software_file' => UploadedFile::fake()->create('App.dmg', 100, 'application/octet-stream'),
        ])->assertOk()->assertJson(['filename' => 'App.dmg']);
    }

    public function test_uploads_still_reject_other_file_types(): void
    {
        $this->postJson(route('admin.tool-files.store'), [
            'tool_file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertJsonValidationErrors('tool_file');

        $this->postJson(route('admin.software-files.store'), [
            'software_file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])->assertJsonValidationErrors('software_file');
    }
}
