<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('threads:refresh-tokens')->daily()->withoutOverlapping(60)->onOneServer();
Schedule::command('threads:prune-posts')->daily()->withoutOverlapping(60)->onOneServer();
Schedule::command('threads:dispatch-search-runs')->everyMinute()->withoutOverlapping(5)->onOneServer();
