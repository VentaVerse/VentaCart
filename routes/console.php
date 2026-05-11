<?php

use App\Models\Automation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ==========================================================================
// Default Venta automations — managed in admin UI (Settings > Automations).
// Each enabled row registers its command with the scheduler using the
// cron_expression stored in DB. Admins edit schedule and enabled flag
// through the admin UI; routes/console.php stays untouched.
//
// The try/catch is deliberate: on a fresh install, Artisan boots this file
// before `php artisan migrate` creates the automations table. Without the
// guard, every artisan command would crash. When the table is missing we
// silently register nothing and let the admin finish installation.
// ==========================================================================
try {
    Automation::enabled()->get()->each(function (Automation $automation) {
        $outputLog = storage_path("logs/automations/{$automation->key}.log");
        @mkdir(dirname($outputLog), 0775, true);

        Schedule::command($automation->command)
            ->cron($automation->cron_expression)
            ->withoutOverlapping()
            ->sendOutputTo($outputLog)
            ->onSuccess(function () use ($automation, $outputLog) {
                $automation->recordRun('success', @file_get_contents($outputLog) ?: null);
            })
            ->onFailure(function () use ($automation, $outputLog) {
                $automation->recordRun('failed', @file_get_contents($outputLog) ?: null);
            });
    });
} catch (\Throwable $e) {
    Log::error('Automation scheduler registration failed', ['error' => $e->getMessage()]);
}
