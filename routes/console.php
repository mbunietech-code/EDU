<?php

use App\Console\Commands\OptimizationScanCommand;
use App\Console\Commands\ProcessExpiryCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessExpiryCommand::class)->dailyAt('00:05');

Schedule::command(OptimizationScanCommand::class)->dailyAt('02:00');

Schedule::command(\App\Console\Commands\ChatAutoReplyCommand::class)->everyMinute();

// Learning (ROOM) — live-room reminders & housekeeping
Schedule::command(\App\Console\Commands\LearningSendRemindersCommand::class)->everyMinute()->withoutOverlapping();

Schedule::command(\App\Console\Commands\LearningCloseStaleRoomsCommand::class)->everyTenMinutes();

// Live classes end for everyone when their planned time is up (also enforced on every classroom poll).
Schedule::command(\App\Console\Commands\LearningEndOverdueRoomsCommand::class)->everyMinute()->withoutOverlapping();

Schedule::command(\App\Console\Commands\LearningPurgeTrashCommand::class)->dailyAt('03:10');

Schedule::command(\App\Console\Commands\LearningCleanupUploadsCommand::class)->hourly();

Schedule::command('inspire')->hourly();