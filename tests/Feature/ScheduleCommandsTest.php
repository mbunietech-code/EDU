<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Tests\TestCase;

class ScheduleCommandsTest extends TestCase
{
    /**
     * The cron runs each scheduled command as `php artisan <name>`; a name with
     * no command behind it fails every time and fills Admin → Error Logs.
     */
    public function test_every_scheduled_command_exists(): void
    {
        $artisan = $this->app->make(Kernel::class);
        $artisan->bootstrap();
        $known = array_keys($artisan->all());

        $events = $this->app->make(Schedule::class)->events();
        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            if (! preg_match("/artisan['\"]?\s+['\"]?([\w:-]+)/", (string) $event->command, $m)) {
                continue;
            }
            $this->assertContains($m[1], $known, "Scheduled command \"{$m[1]}\" is not defined.");
        }
    }
}
