<?php

use App\Console\Commands\ProcessExpiryCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessExpiryCommand::class)->dailyAt('00:05');

Schedule::command('inspire')->hourly();