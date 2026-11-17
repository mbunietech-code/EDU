<?php

namespace App\Console\Commands;

use App\Models\ResearchRequest;
use App\Notifications\Admin\ResearchRequestActivity;
use App\Support\AdminNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Morning deadline warnings for research work requests: the assignee (or,
 * when nobody has it, everyone who can open research requests) hears about
 * work due within two days and about overdue work, once a day per request
 * (deadline_alerted_on).
 */
class ResearchRequestsDeadlineCommand extends Command
{
    protected $signature = 'research-requests:deadlines';

    protected $description = 'Warn staff about research work requests that are due soon or overdue';

    public function handle(): int
    {
        $today = Carbon::today();
        $sent = 0;

        ResearchRequest::query()->active()->with('assignee')
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<=', $today->copy()->addDays(2))
            ->where(fn ($q) => $q->whereNull('deadline_alerted_on')->orWhereDate('deadline_alerted_on', '<', $today))
            ->each(function (ResearchRequest $rr) use ($today, &$sent) {
                $days = (int) $today->diffInDays($rr->deadline, false);
                $when = match (true) {
                    $days < 0 => 'is '.abs($days).' day'.(abs($days) === 1 ? '' : 's').' overdue',
                    $days === 0 => 'is due today',
                    $days === 1 => 'is due tomorrow',
                    default => 'is due in '.$days.' days',
                };
                $message = 'Deadline: '.$rr->reference.' "'.$rr->title.'" '.$when.' (now at '.($rr->stage?->name ?? 'Received').', '.$rr->progress.'%).';

                $assignee = $rr->assignee;
                $recipients = $assignee && $assignee->status === 'active' && $assignee->hasPermission('research_requests.view')
                    ? collect([$assignee])
                    : AdminNotifications::recipients('research_requests.view');

                $recipients->each(fn ($u) => $u->notify(new ResearchRequestActivity($rr, 'deadline', $message)));
                $rr->forceFill(['deadline_alerted_on' => $today])->saveQuietly();
                $sent++;
            });

        $this->info("Deadline warnings sent for {$sent} research request(s).");

        return self::SUCCESS;
    }
}
