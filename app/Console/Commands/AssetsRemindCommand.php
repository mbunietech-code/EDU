<?php

namespace App\Console\Commands;

use App\Models\CompanyAsset;
use App\Models\User;
use App\Notifications\Admin\AssetExpiring;
use Illuminate\Console\Command;

/**
 * Reminds the person who looks after a company asset, and everyone who manages
 * assets, when it ends in 30, 7 and 1 day(s), and when it ends / has ended.
 * Each step is sent once (reminded_days); a new end date starts over.
 */
class AssetsRemindCommand extends Command
{
    protected $signature = 'assets:remind';

    protected $description = 'Remind admins about company assets that end soon';

    /** Reminder steps, in days before the end date. */
    public const STEPS = [30, 7, 1, 0];

    public function handle(): int
    {
        $managers = User::query()->where('is_admin', true)->where('is_guest', false)->where('status', 'active')->get()
            ->filter(fn (User $u) => $u->can('assets.manage'));

        $sent = 0;

        CompanyAsset::query()->with('responsible')->endingWithin(30)->each(function (CompanyAsset $asset) use ($managers, &$sent) {
            $days = $asset->daysLeft();
            $step = collect(self::STEPS)->filter(fn ($s) => $days <= $s)->min();

            if ($step === null || ($asset->reminded_days !== null && $step >= $asset->reminded_days)) {
                return;
            }

            // concat() returns a new collection: the managers list stays the same for the next asset.
            $people = $managers
                ->concat($asset->responsible && $asset->responsible->status === 'active' ? [$asset->responsible] : [])
                ->unique('id');

            $people->each(fn (User $u) => $u->notify(new AssetExpiring($asset, $days)));
            $asset->forceFill(['reminded_days' => $step])->save();
            $sent++;
        });

        $this->info("Reminders sent for {$sent} asset(s).");

        return self::SUCCESS;
    }
}
