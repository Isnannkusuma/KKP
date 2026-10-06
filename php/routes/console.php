<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('faq:generate-from-clusters')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->withoutOverlapping();
