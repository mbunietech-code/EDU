<?php

namespace Tests\Feature\Api;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The mobile app's admin API asks for the same permissions as the website. */
class AdminApiPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function limitedAdmin(array $permissions): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions]);
    }

    public function test_an_admin_without_the_permission_cannot_change_payment_qr_codes_or_settings(): void
    {
        Storage::fake('public');
        $yas = PaymentMethod::create(['code' => 'yas', 'name' => 'MIX by YAS', 'qr_image' => 'payment-methods/real.jpg', 'enabled' => true, 'sort_order' => 1]);

        $payment = Payment::factory()->create();
        Sanctum::actingAs($admin = $this->limitedAdmin(['orders.view']));

        $this->post("/api/admin/payment-methods/{$yas->id}/qr", ['qr_image' => UploadedFile::fake()->image('fake.png')], ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->putJson("/api/admin/payment-methods/{$yas->id}", ['name' => 'YAS', 'account_number' => '0700000000'])->assertForbidden();
        $this->getJson('/api/admin/payment-methods')->assertForbidden();
        $this->postJson('/api/admin/settings/dev', ['show_error_details' => true])->assertForbidden();
        $this->postJson("/api/admin/payments/{$payment->id}/approve")->assertForbidden();
        $this->postJson("/api/admin/users/{$admin->id}/status", ['status' => 'suspended'])->assertForbidden();
        $this->postJson('/api/admin/catalogue/products', ['name' => 'X'])->assertForbidden();

        $this->assertSame('payment-methods/real.jpg', $yas->fresh()->qr_image);

        // What the permission allows still works.
        $this->getJson('/api/admin/orders')->assertOk();
    }

    public function test_the_payment_methods_permission_and_super_admins_still_get_through(): void
    {
        Storage::fake('public');
        $yas = PaymentMethod::create(['code' => 'yas', 'name' => 'MIX by YAS', 'enabled' => true, 'sort_order' => 1]);

        Sanctum::actingAs($this->limitedAdmin(['payment_methods.manage']));
        $this->getJson('/api/admin/payment-methods')->assertOk()->assertJsonPath('data.0.code', 'yas');

        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]));
        $this->post("/api/admin/payment-methods/{$yas->id}/qr", ['qr_image' => UploadedFile::fake()->image('qr.png')], ['Accept' => 'application/json'])
            ->assertOk();
    }
}
