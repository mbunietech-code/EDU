<?php

use App\Console\Commands\OptimizationScanCommand;
use App\Console\Commands\ProcessExpiryCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessExpiryCommand::class)->dailyAt('00:05');

Schedule::command(OptimizationScanCommand::class)->dailyAt('02:00');

Schedule::command(\App\Console\Commands\ChatAutoReplyCommand::class)->everyMinute();

Schedule::command('inspire')->hourly();