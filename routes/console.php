<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run cleanup daily at 2 AM
Schedule::command('users:clean-unverified')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping();
