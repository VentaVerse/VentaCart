<?php

namespace App\Models;

use App\Services\CronExpressionBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A scheduled task whose cadence admins can edit from the Settings > Automations UI.
 *
 * `cron_expression` is always derived from `schedule_preset` + `schedule_params` via
 * CronExpressionBuilder — never hand-crafted. The scheduler in routes/console.php
 * reads enabled() rows and registers each one with ->cron($cron_expression).
 */
class Automation extends Model
{
    protected $fillable = [
        'key',
        'extension',
        'name',
        'command',
        'description',
        'schedule_preset',
        'schedule_params',
        'cron_expression',
        'enabled',
        'last_run_at',
        'last_run_status',
        'last_run_output',
    ];

    protected function casts(): array
    {
        return [
            'schedule_params' => 'array',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Enabled AND runnable: a row owned by an extension only runs while that
     * extension is installed and enabled, because its command only exists
     * then. A disabled module's job is not a failure to report every ten
     * minutes; it is a job that is off with its module.
     */
    public function scopeRunnable($query)
    {
        return $query->where('enabled', true)->where(function ($q) {
            $q->whereNull('extension')
                ->orWhereIn('extension', \App\Models\Extension::where('enabled', true)->select('name'));
        });
    }

    /** The name of the module this job belongs to when that module is not currently enabled; null otherwise. */
    public function heldByDisabledExtension(): ?string
    {
        if (! $this->extension) {
            return null;
        }

        return \App\Models\Extension::where('name', $this->extension)->where('enabled', true)->exists() ? null : $this->extension;
    }

    /**
     * Human-readable schedule description (e.g. "Daily at 08:00") for admin UI.
     */
    public function getScheduleDescriptionAttribute(): string
    {
        return CronExpressionBuilder::describe($this->schedule_preset, $this->schedule_params ?? []);
    }

    /**
     * Update last-run telemetry. Output is capped at 2000 chars to keep the
     * column lean; use storage/logs/automations/*.log for full output.
     */
    /**
     * Unhealthy: the last run failed, or the run the cron expression says
     * should already have happened has not (never run, or the last run
     * predates it). Moved here from the dashboard so an extension's own
     * settings page can show the same verdict for its own automation.
     */
    public function isUnhealthy(): bool
    {
        if ($this->last_run_status === 'failed') {
            return true;
        }
        if (! $this->cron_expression) {
            return false;
        }
        try {
            $expectedRun = (new \Cron\CronExpression($this->cron_expression))->getPreviousRunDate(now());
        } catch (\Throwable) {
            return false;
        }

        return ! $this->last_run_at || $this->last_run_at->lt($expectedRun);
    }

    /**
     * Has the heartbeat (`schedule:run`, one crontab line) fired at all
     * lately? True when no enabled automation has run in the last hour. The
     * one signal that separates "this job is off" from "nothing on this
     * server runs any job", which are fixed in different places.
     */
    public static function heartbeatMissing(): bool
    {
        $latest = static::enabled()->max('last_run_at');

        return $latest === null || \Illuminate\Support\Carbon::parse($latest)->lt(now()->subHour());
    }

    public function recordRun(string $status, ?string $output = null): void
    {
        $this->update([
            'last_run_at' => now(),
            'last_run_status' => $status,
            'last_run_output' => $output ? Str::limit($output, 2000, '…') : null,
        ]);
    }
}
