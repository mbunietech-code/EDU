<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\Admin\SecurityAlert;
use App\Services\AdminAlertService;
use App\Services\SecurityScanner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Daily (full) and hourly (--quick) check for signs of a break-in.
 *
 * - A NEW problem is emailed at once (SMS too when critical) and shown in the
 *   admin notification bell. The same problem is not re-sent for 7 days.
 * - --report sends the daily summary email, even when all is clean.
 * - --accept=all takes the current cron jobs, SSH keys, admins and flagged
 *   files as known-good after you have checked them; --accept=<key> one item.
 *
 * Read-only: nothing is deleted or moved. The latest result is kept in
 * storage/app/security/last-scan.json and storage/logs/security.log.
 */
class SecurityScanCommand extends Command
{
    protected $signature = 'security:scan
        {--quick : Only recently changed files, processes, cron, SSH keys, admins and git}
        {--report : Email the daily summary even when nothing is new}
        {--accept= : "all" or a finding key to mark as checked and safe}
        {--silent : Do not email or notify, only print}';

    protected $description = 'Look for web shells, miners, changed code, new SSH keys/cron jobs/admins and leaked secrets';

    private const REALERT_DAYS = 7;

    public function handle(SecurityScanner $scanner, AdminAlertService $alerts): int
    {
        $state = $this->loadState();
        $firstRun = ! isset($state['baseline']);
        $quick = (bool) $this->option('quick');

        $findings = $scanner->run($quick, $state['baseline'] ?? []);

        // First run, or the admin accepted everything: today's cron/SSH/admins become the baseline.
        $accept = (string) $this->option('accept');
        if ($firstRun || $accept === 'all') {
            $state['baseline'] = array_merge($state['baseline'] ?? [], $scanner->seen);
            $findings = array_values(array_filter($findings, fn ($f) => ! in_array($f['check'], ['crontab', 'ssh', 'admins'], true)));
        }
        if ($accept !== '') {
            foreach ($findings as $f) {
                if ($accept === 'all' || $accept === $f['key']) {
                    $state['accepted'][$f['key']] = now()->toIso8601String();
                }
            }
        }

        $findings = array_values(array_filter($findings, fn ($f) => ! isset($state['accepted'][$f['key']])));
        $problems = array_values(array_filter($findings, fn ($f) => $f['level'] !== 'info'));

        // Alert only what is new (or not repeated for a week).
        $new = array_values(array_filter($problems, function ($f) use ($state) {
            $at = $state['alerted'][$f['key']] ?? null;

            return $at === null || \Illuminate\Support\Carbon::parse($at)->diffInDays(now()) >= self::REALERT_DAYS;
        }));

        $this->printFindings($findings);
        $this->writeLog($quick, $findings);

        if (! $this->option('silent')) {
            if ($new !== []) {
                $this->sendAlert($alerts, $new);
                foreach ($new as $f) {
                    $state['alerted'][$f['key']] = now()->toIso8601String();
                }
            }
            if ($this->option('report')) {
                $this->dailyReport($alerts, $problems, $findings, $firstRun);
            }
        }

        // Forget alerts for problems that are gone, so they alert again if they come back.
        // Only after a full scan: the quick one does not look at everything.
        if (! $quick) {
            $current = array_flip(array_column($problems, 'key'));
            $state['alerted'] = array_intersect_key($state['alerted'] ?? [], $current);
        }
        $state['last_'.($quick ? 'quick' : 'full')] = now()->toIso8601String();
        $this->saveState($state);
        @file_put_contents($this->dir().'/last-scan.json', json_encode([
            'at' => now()->toIso8601String(), 'quick' => $quick, 'findings' => $findings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return collect($problems)->contains('level', 'critical') ? self::FAILURE : self::SUCCESS;
    }

    /** @param list<array{level:string,check:string,title:string,detail:string,key:string}> $new */
    private function sendAlert(AdminAlertService $alerts, array $new): void
    {
        $critical = collect($new)->where('level', 'critical')->count();
        $subject = ($critical ? 'URGENT security alert' : 'Security warning').' - '.parse_url((string) config('app.url'), PHP_URL_HOST);

        $body = "The security scan found something new on the server:\n\n"
            .$this->lines($new)
            ."\n\nNothing was deleted or moved. Check each item; if it is yours and safe, mark it with:\n"
            ."php artisan security:scan --accept=<key>   (or --accept=all after checking everything)\n";

        try {
            $alerts->send($subject, $body, $critical > 0);
        } catch (Throwable $e) {
            $this->error('Could not send the alert: '.$e->getMessage());
        }

        try {
            $admins = User::query()->where('is_admin', true)->get()->filter(fn (User $u) => $u->isSuperAdmin());
            foreach (array_slice($new, 0, 10) as $f) {
                foreach ($admins as $admin) {
                    $admin->notify(new SecurityAlert($f['title'], $f['detail']));
                }
            }
        } catch (Throwable $e) {
            $this->error('Could not add the admin notification: '.$e->getMessage());
        }
    }

    /**
     * @param  list<array<string,string>>  $problems
     * @param  list<array<string,string>>  $findings
     */
    private function dailyReport(AdminAlertService $alerts, array $problems, array $findings, bool $firstRun): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $critical = collect($problems)->where('level', 'critical')->count();

        if ($problems === []) {
            $subject = "Security check OK - {$host} - ".now()->format('d M Y');
            $body = "Daily security check: nothing suspicious found.\n\nChecked: web shells and runnable files in uploads on every site of the hosting account, "
                ."hidden programs/miners, code changed outside a deploy (git), cron jobs, SSH keys, admin accounts, debug mode, "
                ."and secret files reachable from the internet.\n";
        } else {
            $subject = ($critical ? "Security: {$critical} URGENT" : 'Security: '.count($problems).' to check')." - {$host} - ".now()->format('d M Y');
            $body = "Daily security check: these still need attention.\n\n".$this->lines($problems);
        }

        $info = array_values(array_filter($findings, fn ($f) => $f['level'] === 'info'));
        if ($info !== []) {
            $body .= "\n\nFor information:\n".$this->lines($info);
        }
        if ($firstRun) {
            $body .= "\n\nFirst run: today's cron jobs, SSH keys and admins were saved as the known-good list. Any change from now on is reported.";
        }

        try {
            $alerts->send($subject, $body, false);
        } catch (Throwable $e) {
            $this->error('Could not send the daily report: '.$e->getMessage());
        }
    }

    /** @param list<array<string,string>> $items */
    private function lines(array $items): string
    {
        return collect($items)
            ->sortBy(fn ($f) => ['critical' => 0, 'warn' => 1, 'info' => 2][$f['level']] ?? 3)
            ->map(fn ($f) => '['.strtoupper($f['level'])."] {$f['title']}\n    {$f['detail']}\n    key: {$f['key']}")
            ->take(60)
            ->implode("\n");
    }

    /** @param list<array<string,string>> $findings */
    private function printFindings(array $findings): void
    {
        if ($findings === []) {
            $this->info('Nothing suspicious found.');

            return;
        }
        $this->table(['Level', 'Check', 'What', 'Where', 'Key'], array_map(
            fn ($f) => [$f['level'], $f['check'], $f['title'], mb_strimwidth($f['detail'], 0, 90, '…'), mb_strimwidth($f['key'], 0, 50, '…')],
            $findings
        ));
    }

    /** @param list<array<string,string>> $findings */
    private function writeLog(bool $quick, array $findings): void
    {
        $line = '['.now()->toDateTimeString().'] '.($quick ? 'quick' : 'full').' scan: '
            .count($findings).' finding(s)'.($findings ? ': '.implode('; ', array_map(fn ($f) => "{$f['level']}: {$f['title']} ({$f['detail']})", $findings)) : '')."\n";
        @file_put_contents(storage_path('logs/security.log'), $line, FILE_APPEND);
    }

    private function dir(): string
    {
        $dir = storage_path('app/security');
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return $dir;
    }

    /** @return array<string,mixed> */
    private function loadState(): array
    {
        $file = $this->dir().'/state.json';
        $state = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($state) ? $state : [];
    }

    /** @param array<string,mixed> $state */
    private function saveState(array $state): void
    {
        file_put_contents($this->dir().'/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod($this->dir().'/state.json', 0600);
    }
}
