<?php

namespace App\Console\Commands;

use App\Services\VpnPlanSync;
use Illuminate\Console\Command;
use RuntimeException;

class MvpnSyncPlansCommand extends Command
{
    protected $signature = 'mvpn:sync-plans';

    protected $description = 'Copy the Mbunie VPN plans (name, days, TZS price) onto the EduHub VPN product';

    public function handle(VpnPlanSync $sync): int
    {
        try {
            $s = $sync->sync();
        } catch (RuntimeException $e) {
            $this->warn('VPN unreachable: '.$e->getMessage());

            return self::SUCCESS;
        }
        $this->info("created {$s['created']}, updated {$s['updated']}, switched off {$s['disabled']}");

        return self::SUCCESS;
    }
}
