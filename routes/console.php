<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('fundraiser:outbox --limit=100')->everyMinute()->withoutOverlapping();
