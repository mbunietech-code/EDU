<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\StickyNote;
use App\Models\User;
use App\Models\VaultEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StickyNotesAndVaultTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attrs = []): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => 'admin', 'email_verified_at' => now()] + $attrs);
    }

    private function superAdmin(): User
    {
        return $this->admin(['role' => 'super_admin']);
    }

    public function test_any_admin_adds_a_note_and_everyone_sees_it_but_only_the_author_changes_it(): void
    {
        $dmo = $this->admin(['permissions' => ['chat.view']]);
        $other = $this->admin(['permissions' => []]);

        $this->actingAs($dmo)->post(route('admin.sticky-notes.store'), ['body' => 'Call the bank at 10', 'color' => 'green'])->assertRedirect();
        $note = StickyNote::firstOrFail();

        $this->actingAs($other)->get(route('admin.dashboard'))->assertOk()->assertSee('Call the bank at 10');
        $this->actingAs($other)->put(route('admin.sticky-notes.update', $note), ['body' => 'x', 'color' => 'yellow'])->assertForbidden();
        $this->actingAs($other)->delete(route('admin.sticky-notes.destroy', $note))->assertForbidden();

        $this->actingAs($dmo)->put(route('admin.sticky-notes.update', $note), ['body' => 'Call the bank at 11', 'color' => 'blue'])->assertRedirect();
        $this->assertSame('Call the bank at 11', $note->fresh()->body);
        $this->actingAs($this->superAdmin())->delete(route('admin.sticky-notes.destroy', $note))->assertRedirect();
        $this->assertSame(0, StickyNote::count());
    }

    public function test_the_vault_needs_an_explicit_permission_even_for_unrestricted_admins(): void
    {
        $this->actingAs($this->admin(['permissions' => null]))->get(route('admin.vault.pin'))->assertForbidden();
        $this->actingAs($this->admin(['permissions' => ['finance.access']]))->get(route('admin.vault.pin'))->assertForbidden();
        $this->actingAs($this->admin(['permissions' => ['vault.view']]))->get(route('admin.vault.pin'))->assertOk();
    }

    public function test_pin_unlocks_and_secrets_are_encrypted_and_only_shown_on_request(): void
    {
        Setting::set('vault_pin_hash', Hash::make('123456'), 'string', 'vault');
        $viewer = $this->admin(['permissions' => ['vault.view']]);
        $entry = VaultEntry::create(['name' => 'Hostinger', 'category' => 'hosting', 'username' => 'ops@mbunie', 'password' => 'S3cret!pass']);

        $this->assertStringNotContainsString('S3cret!pass', (string) DB::table('vault_entries')->value('password'));

        $this->actingAs($viewer)->get(route('admin.vault.index'))->assertRedirect(route('admin.vault.pin'));
        $this->actingAs($viewer)->postJson(route('admin.vault.reveal', $entry))->assertStatus(423);

        $this->actingAs($viewer)->post(route('admin.vault.pin.verify'), ['pin' => '000000'])->assertSessionHasErrors('pin');
        $this->actingAs($viewer)->post(route('admin.vault.pin.verify'), ['pin' => '123456'])->assertRedirect(route('admin.vault.index'));

        $this->actingAs($viewer)->get(route('admin.vault.index'))->assertOk()->assertSee('Hostinger')->assertDontSee('S3cret!pass');
        $this->actingAs($viewer)->postJson(route('admin.vault.reveal', $entry))->assertOk()->assertJson(['password' => 'S3cret!pass']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'vault_entry_viewed', 'entity_id' => $entry->id]);

        // view only: cannot change
        $this->actingAs($viewer)->delete(route('admin.vault.destroy', $entry))->assertForbidden();
    }

    public function test_a_manager_keeps_the_password_when_the_field_is_left_blank(): void
    {
        Setting::set('vault_pin_hash', Hash::make('123456'), 'string', 'vault');
        $manager = $this->admin(['permissions' => ['vault.view', 'vault.manage']]);
        $this->actingAs($manager)->withSession(['vault_unlocked_at' => now()->timestamp]);

        $this->post(route('admin.vault.store'), ['name' => 'Instagram', 'category' => 'social', 'password' => 'first'])->assertRedirect();
        $entry = VaultEntry::firstOrFail();

        $this->put(route('admin.vault.update', $entry), ['name' => 'Instagram main', 'category' => 'social', 'password' => ''])->assertRedirect();
        $this->assertSame('first', $entry->fresh()->password);
        $this->assertSame('Instagram main', $entry->fresh()->name);
    }

    public function test_only_a_super_admin_sets_the_pin(): void
    {
        $this->actingAs($this->admin(['permissions' => ['vault.view']]))
            ->post(route('admin.vault.pin.set'), ['new_pin' => '111111', 'new_pin_confirmation' => '111111'])->assertForbidden();

        $this->actingAs($this->superAdmin())
            ->post(route('admin.vault.pin.set'), ['new_pin' => '111111', 'new_pin_confirmation' => '111111'])->assertRedirect(route('admin.vault.pin'));
        $this->assertTrue(Hash::check('111111', (string) Setting::get('vault_pin_hash')));
    }
}
