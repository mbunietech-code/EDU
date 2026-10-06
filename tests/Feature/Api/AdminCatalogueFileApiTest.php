<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCatalogueFileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        config(['software.download_disk' => 'private']);
    }

    public function test_admin_uploads_and_replaces_a_software_installer(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::create(['name' => 'SPSS', 'slug' => 'spss', 'type' => 'software', 'status' => 'published', 'price' => 1000, 'software_key' => 'KEY']);

        $this->post("/api/admin/catalogue/products/{$product->id}/software-file", [
            'file' => UploadedFile::fake()->create('spss.dmg', 200),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.name', 'spss.dmg');
        $first = $product->fresh()->software_file;
        Storage::disk('private')->assertExists($first);

        // Replacing deletes the old file.
        $this->post("/api/admin/catalogue/products/{$product->id}/software-file", [
            'file' => UploadedFile::fake()->create('spss-setup.exe', 200),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.name', 'spss-setup.exe');
        Storage::disk('private')->assertMissing($first);

        $this->getJson("/api/admin/catalogue/products/{$product->id}")->assertJsonPath('data.software_file.has_file', true);

        $this->post("/api/admin/catalogue/products/{$product->id}/software-file", [
            'file' => UploadedFile::fake()->create('notes.pdf', 10),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->deleteJson("/api/admin/catalogue/products/{$product->id}/software-file")->assertOk()
            ->assertJsonPath('data.has_file', false);
    }

    public function test_subscription_products_have_no_installer(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::create(['name' => 'ChatGPT Plus', 'slug' => 'chatgpt', 'type' => 'subscription', 'status' => 'published', 'price' => 1000]);

        $this->post("/api/admin/catalogue/products/{$product->id}/software-file", [
            'file' => UploadedFile::fake()->create('a.exe', 10),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_tool_file_and_permissions(): void
    {
        $tool = Tool::create(['name' => 'EndNote', 'slug' => 'endnote', 'price' => 20000, 'status' => 'published']);

        Sanctum::actingAs(User::factory()->admin()->create(['role' => 'admin', 'permissions' => ['orders.view']]));
        $this->post("/api/admin/catalogue/tools/{$tool->id}/file", ['file' => UploadedFile::fake()->create('e.zip', 10)], ['Accept' => 'application/json'])
            ->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->post("/api/admin/catalogue/tools/{$tool->id}/file", ['file' => UploadedFile::fake()->create('endnote.zip', 10)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.name', 'endnote.zip');
        $this->getJson("/api/admin/catalogue/tools/{$tool->id}")->assertJsonPath('data.file.has_file', true);

        $this->deleteJson("/api/admin/catalogue/tools/{$tool->id}/file")->assertOk()->assertJsonPath('data.has_file', false);
        $this->assertSame(0, $tool->downloads()->count());
    }
}
