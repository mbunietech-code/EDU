<?php

namespace Tests\Feature\Learning;

use App\Models\LearningRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The live classes / meetings promo on the public home page: always shown,
 * lists only public rooms that are live or upcoming.
 */
class HomeLiveClassesPromoTest extends TestCase
{
    use RefreshDatabase;

    private function room(User $host, string $title, array $attrs = []): LearningRoom
    {
        return LearningRoom::create(array_merge([
            'title' => $title,
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
        ], $attrs));
    }

    public function test_guests_see_the_promo_even_without_sessions(): void
    {
        $this->get(route('public.home'))->assertOk()
            ->assertSee('Live classes, online meetings &amp; conferences', false)
            ->assertSee('Learn live. Meet online.')
            ->assertSee('Book a meeting or conference')
            ->assertSee(route('register'))
            ->assertDontSee('Upcoming live classes');
    }

    public function test_only_public_live_or_upcoming_rooms_are_listed(): void
    {
        $host = User::factory()->create(['can_teach' => true, 'email' => 'host-private@example.test']);
        $this->room($host, 'Open Python class');
        $this->room($host, 'Live study hall', ['status' => 'live', 'started_at' => now()]);
        $this->room($host, 'Hidden private meeting', ['access' => 'private']);
        $this->room($host, 'Hidden course class', ['access' => 'course']);
        $this->room($host, 'Hidden draft class', ['status' => 'draft']);
        $this->room($host, 'Hidden finished class', ['status' => 'completed', 'scheduled_at' => now()->subDay()]);
        $this->room($host, 'Hidden overdue class', ['scheduled_at' => now()->subDay()]);

        $this->get(route('public.home'))->assertOk()
            ->assertSee('1 class live right now')
            ->assertSee('Open Python class')
            ->assertSee('Live study hall')
            ->assertDontSee('Hidden')
            ->assertDontSee('host-private@example.test');
    }

    public function test_signed_in_members_are_sent_to_the_rooms_list(): void
    {
        $this->actingAs(User::factory()->create())->get(route('public.home'))->assertOk()
            ->assertSee('Browse live classes')
            ->assertSee(route('learn.rooms.index'));
    }
}
