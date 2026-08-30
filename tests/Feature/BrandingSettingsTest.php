<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_upload_a_logo_and_favicon(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'favicon' => UploadedFile::fake()->create('favicon.png', 4, 'image/png'),
        ]);

        $response->assertRedirect();

        $logoPath = Setting::get(Branding::LOGO_KEY);
        $faviconPath = Setting::get(Branding::FAVICON_KEY);

        $this->assertNotEmpty($logoPath);
        $this->assertNotEmpty($faviconPath);
        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertExists($faviconPath);

        Branding::forget();
        $this->assertNotNull(Branding::logoUrl());
        $this->assertNotNull(Branding::faviconUrl());
    }

    public function test_logo_falls_back_to_mark_when_unset(): void
    {
        $this->assertNull(Branding::logoUrl());

        $this->blade('<x-brand-mark />')->assertSee('M');
    }

    public function test_uploading_a_new_logo_replaces_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('old.png'),
        ]);
        $old = Setting::get(Branding::LOGO_KEY);

        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('new.png'),
        ]);
        $new = Setting::get(Branding::LOGO_KEY);

        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_admin_can_remove_the_logo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);
        $path = Setting::get(Branding::LOGO_KEY);

        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'remove_logo' => '1',
        ]);

        $this->assertSame('', Setting::get(Branding::LOGO_KEY));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_rejects_non_image_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->create('evil.php', 10, 'text/php'),
        ])->assertSessionHasErrors('logo');
    }

    public function test_non_admin_cannot_change_branding(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.branding.update'), [])
            ->assertForbidden();
    }
}
