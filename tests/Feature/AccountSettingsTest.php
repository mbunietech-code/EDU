<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The account button in the top bar opens Profile and Settings; Settings
 * changes the sign-in email (confirmed with the password) and the password.
 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['email' => 'me@example.com', 'password' => Hash::make('secret-pass-1')]);
    }

    public function test_the_top_bar_menu_links_profile_and_settings(): void
    {
        $this->actingAs($this->member())->get(route('user.profile.edit'))
            ->assertOk()
            ->assertSee(route('user.settings'), false)
            ->assertSee('Sign out');

        $admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('user.profile.edit'), false)
            ->assertSee(route('user.settings'), false);
        // Admins keep their own panel around these pages.
        $this->get(route('user.settings'))->assertOk()->assertSee('Admin |', false);
    }

    public function test_changing_the_email_needs_the_password(): void
    {
        $user = $this->member();
        $this->actingAs($user);

        $this->get(route('user.settings'))->assertOk()->assertSee('me@example.com');

        $this->patch(route('user.settings.email'), ['email' => 'new@example.com', 'email_password' => 'wrong'])
            ->assertSessionHasErrors('email_password');
        $this->assertSame('me@example.com', $user->fresh()->email);

        $this->patch(route('user.settings.email'), ['email' => 'new@example.com', 'email_password' => 'secret-pass-1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_the_new_email_must_be_free(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = $this->member();

        $this->actingAs($user)
            ->patch(route('user.settings.email'), ['email' => 'taken@example.com', 'email_password' => 'secret-pass-1'])
            ->assertSessionHasErrors('email');
    }

    public function test_the_password_changes_from_settings(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->patch(route('user.profile.password'), [
                'current_password' => 'secret-pass-1',
                'password' => 'New-pass-2026!x',
                'password_confirmation' => 'New-pass-2026!x',
            ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('New-pass-2026!x', $user->fresh()->password));
    }

    public function test_profile_saves_the_name_but_not_the_email(): void
    {
        $user = $this->member();

        $this->actingAs($user)
            ->patch(route('user.profile.update'), ['name' => 'Asha', 'email' => 'sneaky@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Asha', $user->fresh()->name);
        $this->assertSame('me@example.com', $user->fresh()->email);
    }
}
