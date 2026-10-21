<?php

use Illuminate\Support\Facades\Schedule;

// Times are Nairobi time (APP_TIMEZONE).
Schedule::command('kertai:daily')->dailyAt('07:30')->withoutOverlapping()->onOneServer();
Schedule::command('kertai:reconcile')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('kertai:backup')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->weekly();
