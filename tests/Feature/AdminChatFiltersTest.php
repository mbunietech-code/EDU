<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminChatFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function chat(string $name, array $messages, array $userAttrs = []): Conversation
    {
        $user = User::factory()->create(['name' => $name, 'is_admin' => false] + $userAttrs);
        $c = Conversation::create(['user_id' => $user->id]);

        foreach ($messages as [$fromAdmin, $body, $read, $daysAgo]) {
            $m = $c->messages()->create(['is_from_admin' => $fromAdmin, 'type' => 'text', 'body' => $body, 'is_read' => $read]);
            $m->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        }

        return $c;
    }

    private function seedChats(): void
    {
        // Juma: unread message from customer, waiting for us
        $this->chat('Juma Waiting', [[false, 'Nisaidie na malipo', false, 0]]);
        // Asha: we replied last
        $this->chat('Asha Replied', [[false, 'Habari', true, 10], [true, 'Karibu sana', true, 10]]);
        // Neema: old, read, customer spoke last, online now
        $this->chat('Neema Online', [[false, 'Asante', true, 40]], ['last_seen_at' => now()]);
    }

    public function test_tabs_split_conversations_and_show_counts(): void
    {
        $this->seedChats();

        $this->actingAs($this->admin)->get(route('admin.chat.index'))
            ->assertOk()->assertSee('Juma Waiting')->assertSee('Asha Replied')->assertSee('Neema Online');

        $this->actingAs($this->admin)->get(route('admin.chat.index', ['status' => 'unread']))
            ->assertSee('Juma Waiting')->assertDontSee('Asha Replied')->assertDontSee('Neema Online');

        $this->actingAs($this->admin)->get(route('admin.chat.index', ['status' => 'awaiting']))
            ->assertSee('Juma Waiting')->assertSee('Neema Online')->assertDontSee('Asha Replied');

        $this->actingAs($this->admin)->get(route('admin.chat.index', ['status' => 'replied']))
            ->assertSee('Asha Replied')->assertDontSee('Juma Waiting');

        $response = $this->actingAs($this->admin)->get(route('admin.chat.index', ['status' => 'online']))
            ->assertSee('Neema Online')->assertDontSee('Juma Waiting');

        $this->assertSame(['all' => 3, 'unread' => 1, 'awaiting' => 2, 'replied' => 1, 'online' => 1], $response->viewData('counts'));
    }

    public function test_search_matches_message_text_and_period_limits_by_activity(): void
    {
        $this->seedChats();

        $this->actingAs($this->admin)->get(route('admin.chat.index', ['search' => 'malipo']))
            ->assertSee('Juma Waiting')->assertDontSee('Asha Replied');

        $this->actingAs($this->admin)->get(route('admin.chat.index', ['period' => '7d']))
            ->assertSee('Juma Waiting')->assertDontSee('Asha Replied')->assertDontSee('Neema Online');

        $this->actingAs($this->admin)->get(route('admin.chat.index', ['period' => '30d']))
            ->assertSee('Asha Replied')->assertDontSee('Neema Online');
    }

    public function test_list_is_paginated(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->chat("Mteja {$i}", [[false, 'Habari', true, 0]]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.chat.index'))->assertOk();
        $this->assertCount(20, $response->viewData('conversations'));

        $page2 = $this->actingAs($this->admin)->get(route('admin.chat.index', ['page' => 2]))->assertOk();
        $this->assertCount(5, $page2->viewData('conversations'));
    }
}
