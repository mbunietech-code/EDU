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

Schedule::command(\App\Console\Commands\PaymentsSyncGatewayCommand::class)->everyMinute()->withoutOverlapping();

// Paid Mbunie VPN orders the VPN server couldn't activate yet (it was unreachable).
Schedule::command(\App\Console\Commands\MvpnRetryActivationsCommand::class)->everyFiveMinutes()->withoutOverlapping();

// Company assets ending soon, and team tasks due / overdue: morning reminders.
Schedule::command(\App\Console\Commands\AssetsRemindCommand::class)->dailyAt('07:00')->withoutOverlapping();
Schedule::command(\App\Console\Commands\TasksRemindCommand::class)->dailyAt('07:05')->withoutOverlapping();

Schedule::command('inspire')->hourly();
// Copy the VPN's plans onto the EduHub "vpn" product (prices live in Mbunie VPN → Plans).
Schedule::command('mvpn:sync-plans')->hourly()->withoutOverlapping();
