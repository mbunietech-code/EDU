<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Looks for signs of a break-in on the hosting account: web shells, PHP
 * files in upload folders, miners, code changed outside a deploy, new SSH
 * keys / cron jobs / admins, debug mode on, and secrets readable from the web.
 *
 * Read-only: it never deletes or moves anything. Each finding has a stable
 * key so the same problem is alerted once, not every hour.
 *
 * Finding: ['level' => critical|warn|info, 'check' => string, 'title' => string, 'detail' => string, 'key' => string]
 */
class SecurityScanner
{
    /** Extensions a web server may run. */
    private const EXEC_EXT = '/\.(php\d*|phtml|pht|phar|phps|shtml|cgi|pl|py|sh|asp|aspx|jsp)$/i';

    /** Double extensions used to sneak PHP past upload checks: x.php.jpg */
    private const DOUBLE_EXT = '/\.(php\d*|phtml|phar)\.[a-z0-9]+$/i';

    /** Web shell and miner fingerprints. */
    private const SIGNATURES = [
        'eval of encoded code' => '/\beval\s*\(\s*(base64_decode|gzinflate|gzuncompress|gzdecode|str_rot13|rawurldecode|hex2bin)\s*\(/i',
        'runs code from the request' => '/\b(assert|eval|system|passthru|shell_exec|exec|popen|proc_open|pcntl_exec)\s*\(\s*(\$_(GET|POST|REQUEST|COOKIE|SERVER)|stripslashes\s*\(\s*\$_)/i',
        'calls a function named in the request' => '/\$_(GET|POST|REQUEST|COOKIE)\s*\[[^\]]{1,40}\]\s*\(/i',
        'preg_replace /e' => '/preg_replace\s*\(\s*([\'"]).{1,200}\/[a-z]*e[a-z]*\1/i',
        'saves uploaded files directly' => '/move_uploaded_file\s*\(\s*\$_FILES/i',
        'known web shell' => '/(FilesMan|b374k|c99shell|r57shell|IndoXploit|AlfaTeam|ALFA_SHELL|WSOsetcookie|Tiny ?File ?Manager|marijuana shell|0byt3m1n1)/i',
        'crypto miner' => '/(xmrig|stratum\+tcp|cryptonight|minexmr|nanopool\.org|supportxmr)/i',
    ];

    /** Folders skipped entirely. */
    private const SKIP_DIRS = ['.git', 'storage/framework', 'storage/logs', 'bootstrap/cache', '.cache', '.npm', '.composer'];

    private const MAX_READ = 400_000;

    private const MAX_FILES = 300_000;

    private bool $quick = false;

    private int $since = 0;

    /** @var list<array{level:string,check:string,title:string,detail:string,key:string}> */
    private array $findings = [];

    /** @var array<string,mixed> */
    private array $baseline = [];

    /** @var array<string,mixed> what was seen this run, to become the next baseline when accepted */
    public array $seen = [];

    /**
     * @param  array<string,mixed>  $baseline  crontab / ssh keys / admins accepted earlier
     * @return list<array{level:string,check:string,title:string,detail:string,key:string}>
     */
    public function run(bool $quick, array $baseline = []): array
    {
        $this->quick = $quick;
        $this->baseline = $baseline;
        $this->findings = [];
        $this->seen = [];
        $hours = $quick ? (int) config('security.quick_hours', 2) : (int) config('security.recent_hours', 72);
        $this->since = time() - max(1, $hours) * 3600;

        $checks = [
            'files' => fn () => $this->scanFiles(),
            'git' => fn () => $this->checkGit(),
            'processes' => fn () => $this->checkProcesses(),
            'crontab' => fn () => $this->checkCrontab(),
            'ssh' => fn () => $this->checkSshKeys(),
            'admins' => fn () => $this->checkAdmins(),
        ];
        if (! $quick) {
            $checks['config'] = fn () => $this->checkAppConfig();
            $checks['web'] = fn () => $this->checkWebExposure();
        }

        foreach ($checks as $name => $check) {
            try {
                $check();
            } catch (Throwable $e) {
                $this->add('warn', $name, "The {$name} check could not run", $e->getMessage(), "error:{$name}");
            }
        }

        return $this->findings;
    }

    /** @return list<string> */
    public function roots(): array
    {
        $roots = config('security.roots') ?: [];
        if ($roots === []) {
            // ~/domains/<site>/public_html → scan every site on the account.
            $domains = dirname(base_path(), 2);
            $roots = basename($domains) === 'domains' && is_dir($domains) ? [$domains] : [base_path()];
        }

        return array_values(array_filter($roots, 'is_dir'));
    }

    // ------------------------------------------------------------------ files

    private function scanFiles(): void
    {
        $count = 0;
        foreach ($this->roots() as $root) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                    fn (\SplFileInfo $f) => ! ($f->isDir() && $this->skipDir($f->getPathname()))
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY,
                \RecursiveIteratorIterator::CATCH_GET_CHILD
            );

            foreach ($it as $file) {
                if (++$count > self::MAX_FILES) {
                    $this->add('warn', 'files', 'File scan stopped early', 'More than '.self::MAX_FILES.' files; raise MAX_FILES or narrow SECURITY_SCAN_ROOTS.', 'files:limit');

                    return;
                }
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }
                $this->inspectFile($file);
            }
        }
    }

    /**
     * The app's own vendor/ and node_modules/ (huge, only changed by composer/npm).
     * public/vendor is NOT one of them: the first web shell of 2026 hid there.
     */
    private function isRecentOnly(string $path): bool
    {
        return (bool) preg_match('#(?<!/public)/vendor/|/node_modules/#', $path.'/');
    }

    private function skipDir(string $path): bool
    {
        $p = str_replace('\\', '/', $path);
        // The hourly scan leaves the big package folders to the daily one.
        if ($this->quick && $this->isRecentOnly($p)) {
            return true;
        }
        foreach (self::SKIP_DIRS as $skip) {
            if (str_ends_with($p, '/'.$skip)) {
                return true;
            }
        }

        return false;
    }

    private function inspectFile(\SplFileInfo $file): void
    {
        $path = str_replace('\\', '/', $file->getPathname());
        if ($path === str_replace('\\', '/', __FILE__)) {
            return; // this file holds the signatures it looks for
        }
        $name = $file->getFilename();
        $mtime = $file->getMTime();
        $recent = $mtime >= $this->since;
        $inRecentOnly = $this->isRecentOnly($path);
        $isExec = (bool) preg_match(self::EXEC_EXT, $name) || (bool) preg_match(self::DOUBLE_EXT, $name);

        // 1. Anything runnable inside an upload folder.
        if ($this->isUploadDir($path) && ($isExec || in_array(strtolower($name), ['.htaccess', '.user.ini', 'php.ini'], true))) {
            $this->add('critical', 'files', 'Runnable file in an upload folder', $this->describe($path, $mtime),
                'upload:'.$path.':'.$file->getSize());
        }

        // 2. A program (Linux binary) hidden in the web folders — how XMRig came in.
        $suspectBinary = $file->getSize() > 100_000
            && ($recent || str_starts_with($name, '.') || ! str_contains($name, '.'))
            && ! preg_match('/\.(jpe?g|png|gif|webp|svg|mp4|webm|mp3|pdf|zip|gz|woff2?|ttf|apk|exe|so|db|sqlite)$/i', $name);
        if ($suspectBinary && $this->isElf($path)) {
            $this->add('critical', 'files', 'Linux program inside the web folders', $this->describe($path, $mtime),
                'elf:'.$path.':'.$file->getSize());
        }

        // 3. Web shell signatures in PHP-like files.
        if (! $isExec || ($inRecentOnly && ! $recent) || ($this->quick && ! $recent)) {
            return;
        }
        $code = @file_get_contents($path, false, null, 0, self::MAX_READ);
        if ($code === false || $code === '') {
            return;
        }
        foreach (self::SIGNATURES as $label => $regex) {
            if (preg_match($regex, $code)) {
                $this->add('critical', 'files', "Possible web shell ({$label})", $this->describe($path, $mtime),
                    'sig:'.$path.':'.sha1($code));
                break;
            }
        }

        // 4. Hidden PHP files are almost never legitimate.
        if (str_starts_with($name, '.') && preg_match('/\.(php\d*|phtml|phar)$/i', $name)) {
            $this->add('critical', 'files', 'Hidden PHP file', $this->describe($path, $mtime), 'hidden:'.$path.':'.sha1($code));
        }
    }

    private function isUploadDir(string $path): bool
    {
        return (bool) preg_match('#/(storage/app|public/storage|public/uploads|uploads|wp-content/uploads)/#', $path);
    }

    private function isElf(string $path): bool
    {
        $h = @fopen($path, 'rb');
        if (! $h) {
            return false;
        }
        $magic = fread($h, 4);
        fclose($h);

        return $magic === "\x7fELF";
    }

    private function describe(string $path, int $mtime): string
    {
        return $path.' (changed '.gmdate('Y-m-d H:i', $mtime).' UTC)';
    }

    // -------------------------------------------------------------------- git

    /** Code changed on the server without a deploy, or files added next to it. */
    private function checkGit(): void
    {
        foreach ($this->gitCheckouts() as $repo) {
            $p = $this->process(['git', '-C', $repo, 'status', '--porcelain', '--untracked-files=all'], 90);
            if ($p === null) {
                $this->add('info', 'git', 'git is not available', $repo, 'git:missing');

                return;
            }
            if (! $p->isSuccessful()) {
                $this->add('warn', 'git', 'git status failed', $repo.': '.trim($p->getErrorOutput()), 'git:fail:'.$repo);

                continue;
            }

            foreach (preg_split('/\R/', trim($p->getOutput())) ?: [] as $line) {
                if ($line === '') {
                    continue;
                }
                $status = substr($line, 0, 2);
                $file = trim(substr($line, 3), '"');
                $full = $repo.'/'.$file;
                $runnable = preg_match(self::EXEC_EXT, $file) || str_ends_with($file, '.htaccess') || str_ends_with($file, '.user.ini');
                $hash = is_file($full) ? substr((string) @sha1_file($full), 0, 12) : 'gone';
                $what = $status === '??' ? 'New file not from git' : 'Code changed on the server (not by a deploy)';
                $this->add($runnable ? 'critical' : 'warn', 'git', $what, "{$repo}/{$file}", "git:{$repo}:{$status}:{$file}:{$hash}");
            }
        }
    }

    /** @return list<string> folders that are git checkouts (up to 4 levels down) */
    private function gitCheckouts(): array
    {
        $repos = [];
        foreach ($this->roots() as $root) {
            foreach (['', '/*', '/*/*', '/*/*/*'] as $depth) {
                foreach (glob($root.$depth.'/.git', GLOB_NOSORT) ?: [] as $git) {
                    if (is_dir($git)) {
                        $repos[] = str_replace('\\', '/', dirname($git));
                    }
                }
            }
        }

        return array_values(array_unique($repos));
    }

    // -------------------------------------------------------------- processes

    private function checkProcesses(): void
    {
        $user = function_exists('posix_geteuid') && function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? null) : null;
        $p = $this->process($user ? ['ps', '-u', $user, '-o', 'pid=,pcpu=,etimes=,args='] : ['ps', '-eo', 'pid=,pcpu=,etimes=,args='], 20);
        if ($p === null || ! $p->isSuccessful()) {
            $this->add('info', 'processes', 'Could not list running programs', 'ps is not available here', 'ps:missing');

            return;
        }

        foreach (preg_split('/\R/', trim($p->getOutput())) ?: [] as $line) {
            if (! preg_match('/^\s*(\d+)\s+([\d.]+)\s+(\d+)\s+(.+)$/', $line, $m)) {
                continue;
            }
            [, $pid, $cpu, $secs, $cmd] = $m;
            if (preg_match('/(xmrig|minerd|cpuminer|kdevtmpfsi|kinsing|stratum\+tcp|\/\.lib\/|\/dev\/shm\/|\/tmp\/\.)/i', $cmd)) {
                $this->add('critical', 'processes', 'Miner or hidden program running', "PID {$pid}: ".mb_strimwidth($cmd, 0, 200, '…'), 'proc:'.md5($cmd));
            } elseif ((float) $cpu > 80 && (int) $secs > 900 && ! preg_match('/(php|artisan|git|composer|node|ps )/i', $cmd)) {
                $this->add('warn', 'processes', 'Program using a lot of CPU for a long time', "PID {$pid} ({$cpu}% CPU, ".round($secs / 60).' min): '.mb_strimwidth($cmd, 0, 200, '…'), 'cpu:'.md5($cmd));
            }
        }
    }

    // ----------------------------------------------------------------- crontab

    private function checkCrontab(): void
    {
        $p = $this->process(['crontab', '-l'], 20);
        if ($p === null) {
            $this->add('info', 'crontab', 'Could not read the cron jobs', 'crontab is not available here', 'cron:missing');

            return;
        }
        // Exit 1 with "no crontab for <user>" just means the list is empty.
        if (! $p->isSuccessful() && ! str_contains(strtolower($p->getErrorOutput()), 'no crontab')) {
            $this->add('info', 'crontab', 'Could not read the cron jobs', trim($p->getErrorOutput()), 'cron:unreadable');

            return;
        }
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $p->getOutput()) ?: []), fn ($l) => $l !== '' && ! str_starts_with($l, '#')));
        $this->seen['crontab'] = $lines;

        if (! isset($this->baseline['crontab'])) {
            return; // first run: becomes the baseline
        }
        foreach (array_diff($lines, $this->baseline['crontab']) as $line) {
            $this->add('critical', 'crontab', 'New cron job', mb_strimwidth($line, 0, 300, '…'), 'cron:+'.md5($line));
        }
        foreach (array_diff($this->baseline['crontab'], $lines) as $line) {
            $this->add('warn', 'crontab', 'Cron job removed', mb_strimwidth($line, 0, 300, '…'), 'cron:-'.md5($line));
        }
    }

    // ---------------------------------------------------------------- ssh keys

    private function checkSshKeys(): void
    {
        $home = $this->home();
        $file = $home ? $home.'/.ssh/authorized_keys' : null;
        if (! $file || ! is_readable($file)) {
            $this->seen['ssh_keys'] = [];

            return;
        }

        $keys = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/(ssh-[a-z0-9-]+|ecdsa-[a-z0-9-]+|sk-[a-z0-9@.-]+)\s+([A-Za-z0-9+\/=]+)\s*(.*)$/', trim($line), $m)) {
                $fp = 'SHA256:'.rtrim(base64_encode(hash('sha256', (string) base64_decode($m[2]), true)), '=');
                $keys[$fp] = trim($m[3]) ?: '(no name)';
            }
        }
        $this->seen['ssh_keys'] = $keys;

        if (! isset($this->baseline['ssh_keys'])) {
            return;
        }
        foreach (array_diff_key($keys, $this->baseline['ssh_keys']) as $fp => $name) {
            $this->add('critical', 'ssh', 'New SSH key can log in to the server', "{$name} ({$fp})", 'ssh:+'.$fp);
        }
        foreach (array_diff_key($this->baseline['ssh_keys'], $keys) as $fp => $name) {
            $this->add('info', 'ssh', 'SSH key removed', "{$name} ({$fp})", 'ssh:-'.$fp);
        }
    }

    // ------------------------------------------------------------------ admins

    private function checkAdmins(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        $admins = User::query()->where('is_admin', true)->get()
            ->mapWithKeys(fn (User $u) => [(string) $u->id => $u->email.' ['.$u->roleName().']'])
            ->all();
        $this->seen['admins'] = $admins;

        if (! isset($this->baseline['admins'])) {
            return;
        }
        foreach ($admins as $id => $who) {
            $before = $this->baseline['admins'][$id] ?? null;
            if ($before === null) {
                $this->add('critical', 'admins', 'New admin account', "#{$id} {$who}", 'admin:+'.$id);
            } elseif ($before !== $who) {
                $this->add('warn', 'admins', 'Admin account changed', "#{$id} {$before} → {$who}", 'admin:~'.$id.':'.md5($who));
            }
        }
        foreach (array_diff_key($this->baseline['admins'], $admins) as $id => $who) {
            $this->add('info', 'admins', 'Admin access removed', "#{$id} {$who}", 'admin:-'.$id);
        }
    }

    // ------------------------------------------------------------------ config

    /** Debug mode, dev packages and readable .env files in every Laravel app on the account. */
    private function checkAppConfig(): void
    {
        foreach ($this->laravelApps() as $app) {
            $env = $app.'/.env';
            if (is_readable($env)) {
                $text = (string) file_get_contents($env);
                if (preg_match('/^\s*APP_DEBUG\s*=\s*"?(true|1)"?\s*$/mi', $text)) {
                    $this->add('critical', 'config', 'Debug mode is ON (shows secrets on errors)', $env, 'debug:'.$app);
                }
                if (preg_match('/^\s*APP_ENV\s*=\s*"?(local|dev|development|testing)"?\s*$/mi', $text)) {
                    $this->add('warn', 'config', 'APP_ENV is not production', $env, 'env:'.$app);
                }
                if ((fileperms($env) & 4) !== 0) {
                    $this->add('warn', 'config', '.env can be read by other users (chmod 600)', $env, 'perm:'.$app);
                }
            }
            if (is_dir($app.'/vendor/spatie/laravel-ignition') || is_dir($app.'/vendor/facade/ignition')) {
                $this->add('warn', 'config', 'Development packages installed on the live site (composer install --no-dev)', $app, 'devpkg:'.$app);
            }
        }
    }

    /** @return list<string> */
    private function laravelApps(): array
    {
        $apps = [base_path()];
        foreach ($this->roots() as $root) {
            foreach (['', '/*', '/*/*', '/*/*/*'] as $depth) {
                foreach (glob($root.$depth.'/artisan', GLOB_NOSORT) ?: [] as $artisan) {
                    $apps[] = dirname($artisan);
                }
            }
        }

        return array_values(array_unique(array_map(fn ($p) => str_replace('\\', '/', $p), $apps)));
    }

    /** Files that must never be downloadable from the sites. */
    private function checkWebExposure(): void
    {
        $probes = [
            '/.env' => '/APP_KEY|DB_PASSWORD/',
            '/.git/config' => '/\[core\]/',
            '/storage/logs/laravel.log' => '/\.(ERROR|CRITICAL|WARNING):/',
            '/.env.backup' => '/APP_KEY|DB_PASSWORD/',
            '/composer.lock' => '/"packages"/',
        ];

        foreach (config('security.urls', []) as $base) {
            $base = rtrim($base, '/');
            if (! preg_match('#^https?://#', $base)) {
                continue;
            }
            foreach ($probes as $path => $leak) {
                try {
                    $res = Http::timeout(15)->withoutRedirecting()->withHeaders(['User-Agent' => 'MbunieSecurityScan/1.0'])->get($base.$path);
                } catch (Throwable) {
                    continue;
                }
                if ($res->status() === 200 && preg_match($leak, substr($res->body(), 0, 20000))) {
                    $level = $path === '/composer.lock' ? 'warn' : 'critical';
                    $this->add($level, 'web', 'Secret file can be downloaded from the internet', $base.$path, 'web:'.$base.$path);
                }
            }
        }
    }

    // ----------------------------------------------------------------- helpers

    private function home(): ?string
    {
        $home = getenv('HOME') ?: null;
        if (! $home && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $home = posix_getpwuid(posix_geteuid())['dir'] ?? null;
        }

        return $home ?: null;
    }

    /** @param list<string> $cmd */
    private function process(array $cmd, int $timeout): ?Process
    {
        if (! function_exists('proc_open')) {
            return null;
        }
        try {
            $p = new Process($cmd, null, ['HOME' => $this->home() ?? sys_get_temp_dir()]);
            $p->setTimeout($timeout);
            $p->run();

            // 127 = command not found
            return $p->getExitCode() === 127 ? null : $p;
        } catch (Throwable) {
            return null;
        }
    }

    private function add(string $level, string $check, string $title, string $detail, string $key): void
    {
        $this->findings[] = compact('level', 'check', 'title', 'detail', 'key');
    }
}
