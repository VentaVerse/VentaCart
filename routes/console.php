<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Update currency exchange rates daily at 4pm (ECB publishes ~2:15pm CET)
Schedule::command('currency:update')->dailyAt('16:00');

// Send back-in-stock email notifications every 15 minutes
Schedule::command('stock:notify')->everyFifteenMinutes();

// Send review request emails daily at 10am
Schedule::command('review:send')->dailyAt('10:00');

// Generate SEO suggestions (default monthly on 1st at 3am)
Schedule::command('seo:generate')->monthlyOn(1, '03:00');

// Generate sitemap daily at 4am
Schedule::command('sitemap:generate')->dailyAt('04:00');

// Check low stock products and send admin alerts daily at 8am
Schedule::command('stock:check-low')->dailyAt('08:00');
