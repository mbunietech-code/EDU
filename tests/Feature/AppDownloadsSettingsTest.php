<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\AppDownloads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppDownloadsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_set_a_download_url_and_upload_a_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.settings.downloads.update'), [
            'url_android' => 'https://example.com/MHub.apk',
            'version_android' => 'v1.2.0',
            'file_windows' => UploadedFile::fake()->create('MHub-Setup.exe', 20, 'application/octet-stream'),
        ])->assertRedirect();

        AppDownloads::forget();
        $available = AppDownloads::available();

        $this->assertCount(2, $available);

        $android = collect($available)->firstWhere('platform', 'android');
        $this->assertSame('https://example.com/MHub.apk', $android['url']);
        $this->assertSame('v1.2.0', $android['version']);

        $windows = collect($available)->firstWhere('platform', 'windows');
        $this->assertStringContainsString('/storage/app-downloads/', $windows['url']);
        Storage::disk('public')->assertExists(Setting::get(AppDownloads::key('windows', 'path')));
    }

    public function test_a_url_replaces_a_previously_uploaded_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.downloads.update'), [
            'file_android' => UploadedFile::fake()->create('old.apk', 10),
        ])->assertRedirect();
        $oldPath = Setting::get(AppDownloads::key('android', 'path'));
        $this->assertNotEmpty($oldPath);

        $this->actingAs($admin)->post(route('admin.settings.downloads.update'), [
            'url_android' => 'https://example.com/new.apk',
        ])->assertRedirect();

        $this->assertSame('', Setting::get(AppDownloads::key('android', 'path')));
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_admin_can_remove_a_download(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.downloads.update'), [
            'url_linux' => 'https://example.com/MHub.AppImage',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.settings.downloads.update'), [
            'remove_linux' => '1',
        ])->assertRedirect();

        AppDownloads::forget();
        $this->assertSame([], AppDownloads::available());
    }

    public function test_rejects_a_disallowed_file_extension(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.settings.downloads.update'), [
            'file_windows' => UploadedFile::fake()->create('malware.bat', 5),
        ])->assertSessionHasErrors('file_windows');
    }

    public function test_rejects_a_bad_url(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.downloads.update'), [
            'url_android' => 'not-a-url',
        ])->assertSessionHasErrors('url_android');
    }

    public function test_non_admin_cannot_change_downloads(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.downloads.update'), [])
            ->assertForbidden();
    }

    public function test_download_band_hidden_when_nothing_configured(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Get the MHub app');
    }
}
