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
    public function recordRun(string $status, ?string $output = null): void
    {
        $this->update([
            'last_run_at' => now(),
            'last_run_status' => $status,
            'last_run_output' => $output ? Str::limit($output, 2000, '…') : null,
        ]);
    }
}
