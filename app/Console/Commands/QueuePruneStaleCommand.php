<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop queued jobs that waited too long to be worth running. On shared
 * hosting the queue only runs from the cron; when it stops (as it did from
 * August to October 2026, leaving 6,175 jobs), restarting it must not send
 * customers weeks-old emails or flood the notification bell. Jobs a worker
 * is running right now are never touched.
 */
class QueuePruneStaleCommand extends Command
{
    protected $signature = 'queue:prune-stale
        {--hours= : Age in hours after which a waiting job is dropped (default: queue.stale_after_hours)}
        {--dry-run : List what would be dropped without deleting}';

    protected $description = 'Delete queued jobs that have waited longer than the stale limit';

    public function handle(): int
    {
        $table = config('queue.connections.database.table', 'jobs');
        if (! Schema::hasTable($table)) {
            return self::SUCCESS;
        }

        $hours = (int) ($this->option('hours') ?: config('queue.stale_after_hours', 48));
        $cutoff = now()->subHours(max(1, $hours))->getTimestamp();

        $stale = DB::table($table)->whereNull('reserved_at')->where('created_at', '<', $cutoff);
        $jobs = (clone $stale)->get(['id', 'payload']);

        if ($jobs->isEmpty()) {
            return self::SUCCESS;
        }

        $byType = $jobs->countBy(fn ($j) => class_basename((string) (json_decode($j->payload, true)['displayName'] ?? 'Unknown')))
            ->sortDesc()->all();

        foreach ($byType as $type => $count) {
            $this->line(sprintf('%6d  %s', $count, $type));
        }

        if ($this->option('dry-run')) {
            $this->info("{$jobs->count()} jobs older than {$hours}h would be dropped (dry run, nothing deleted).");

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($jobs->pluck('id')->chunk(500) as $ids) {
            $deleted += DB::table($table)->whereIn('id', $ids->all())->whereNull('reserved_at')->delete();
        }

        ActivityLog::log('queue_stale_jobs_dropped', 'Queue', null, ['hours' => $hours, 'deleted' => $deleted, 'by_type' => $byType]);
        $this->info("Dropped {$deleted} jobs older than {$hours}h without running them.");

        return self::SUCCESS;
    }
}
