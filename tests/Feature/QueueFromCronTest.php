<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as Notifier;
use Tests\TestCase;

class QueueFromCronTest extends TestCase
{
    use RefreshDatabase;

    private function job(int $hoursAgo, ?int $reservedAt = null, string $type = 'App\\Notifications\\ChatMessageReceived'): int
    {
        return DB::table('jobs')->insertGetId([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => $type, 'data' => []]),
            'attempts' => 0,
            'reserved_at' => $reservedAt,
            'available_at' => now()->subHours($hoursAgo)->getTimestamp(),
            'created_at' => now()->subHours($hoursAgo)->getTimestamp(),
        ]);
    }

    public function test_stale_jobs_are_dropped_without_running_and_recent_or_running_ones_stay(): void
    {
        $old = $this->job(24 * 60);                                        // August backlog
        $oldMail = $this->job(72, null, 'App\\Notifications\\User\\PaymentApproved');
        $recent = $this->job(2);
        $running = $this->job(24 * 60, now()->getTimestamp());             // a worker has it now

        $this->artisan('queue:prune-stale', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(4, DB::table('jobs')->count(), 'Dry run deletes nothing.');

        $this->artisan('queue:prune-stale')
            ->expectsOutputToContain('Dropped 2 jobs older than 48h')
            ->assertSuccessful();

        $left = DB::table('jobs')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$recent, $running], $left);
        $this->assertNotContains($old, $left);
        $this->assertNotContains($oldMail, $left);

        $log = ActivityLog::where('action', 'queue_stale_jobs_dropped')->sole();
        $this->assertSame(2, $log->metadata['deleted']);
        $this->assertSame(['ChatMessageReceived' => 1, 'PaymentApproved' => 1], $log->metadata['by_type']);
    }

    public function test_the_limit_comes_from_config_or_the_option(): void
    {
        $this->job(30);
        config(['queue.stale_after_hours' => 24]);
        $this->artisan('queue:prune-stale')->assertSuccessful();
        $this->assertSame(0, DB::table('jobs')->count());

        $this->job(30);
        $this->artisan('queue:prune-stale', ['--hours' => 48])->assertSuccessful();
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_the_cron_prunes_then_works_the_queue_every_minute(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())->values();
        $prune = $events->search(fn ($e) => str_contains((string) $e->command, 'queue:prune-stale'));
        $work = $events->search(fn ($e) => str_contains((string) $e->command, 'queue:work'));

        $this->assertNotFalse($prune);
        $this->assertNotFalse($work);
        $this->assertLessThan($work, $prune, 'Stale jobs are dropped before the worker starts.');

        $worker = $events[$work];
        $this->assertSame('* * * * *', $worker->expression);
        $this->assertStringContainsString('--stop-when-empty', $worker->command);
        $this->assertStringContainsString('--max-time=50', $worker->command);
        $this->assertTrue($worker->withoutOverlapping);
        $this->assertTrue($worker->runInBackground);
    }

    public function test_a_queued_notification_reaches_the_user_when_the_worker_runs(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();

        Notifier::send($user, new QueuedBellNotification('Your payment was approved'));
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(0, $user->notifications()->count(), 'Nothing reaches the user until a worker runs.');

        // --memory: inside a long test run PHPUnit itself is past the 128 MB default,
        // which would make the worker stop at once (exit 12).
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--tries' => 1, '--memory' => 4096])->assertSuccessful();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame('Your payment was approved', $user->notifications()->first()->data['message']);
    }
}

class QueuedBellNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $message)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return ['message' => $this->message];
    }
}
