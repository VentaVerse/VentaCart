<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AutomationUpdateRequest;
use App\Models\Automation;
use App\Services\CronExpressionBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AutomationController extends Controller
{
    public function index()
    {
        // The Automations UI lives inside the Settings tabs. Redirect so the
        // settings controller can gather the full $settings / $currencies /
        // $maskedKeys context the page needs; it also passes $automations.
        return redirect()->route('admin.settings.index', ['tab' => 'automations']);
    }

    public function update(AutomationUpdateRequest $request, Automation $automation): JsonResponse
    {
        $preset = $request->input('schedule_preset');
        $params = $request->input('schedule_params', []);

        // Form inputs arrive as strings — coerce numeric params to int for
        // consistent JSON storage and matching with CronExpressionBuilder.
        $params = array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $params);

        $automation->update([
            'schedule_preset' => $preset,
            'schedule_params' => $params,
            'cron_expression' => CronExpressionBuilder::build($preset, $params),
        ]);

        return response()->json([
            'success' => true,
            'automation' => $this->toArray($automation->fresh()),
        ]);
    }

    /** One save for every automation's enabled flag (the toggle law). */
    public function updateAll(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.enabled' => ['boolean'],
        ]);

        $automations = Automation::whereIn('id', array_keys($validated['rows']))->get()->keyBy('id');
        foreach ($validated['rows'] as $id => $row) {
            $automations->get((int) $id)?->update([
                'enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            ]);
        }

        return redirect()->route('admin.settings.section', 'automations')->with('success', 'Automations saved.');
    }

    public function toggle(Automation $automation): JsonResponse
    {
        $automation->update(['enabled' => !$automation->enabled]);

        return response()->json([
            'success' => true,
            'enabled' => $automation->enabled,
            'message' => $automation->name . ' ' . ($automation->enabled ? 'enabled.' : 'disabled.'),
        ]);
    }

    /**
     * Manually trigger an automation's artisan command.
     *
     * Runs synchronously — blocks the HTTP request for the command's duration.
     * Safe for Venta's commands which finish in seconds (currency:update,
     * sitemap:generate, stock:notify, etc.). If a future command becomes
     * long-running, wrap it in a queue job and ->dispatch() it from its handle().
     */
    public function runNow(Automation $automation): JsonResponse
    {
        if (!$automation->enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Enable the automation first. Disabled tasks cannot be run manually.',
            ], 422);
        }

        // Cap the single-invocation runtime at 5 minutes so a wedged command
        // doesn't hold an HTTP worker indefinitely. Most Venta commands finish
        // well under this.
        @set_time_limit(300);

        try {
            Artisan::call($automation->command);
            $output = Artisan::output();
            $automation->recordRun('success', $output);

            return response()->json([
                'success' => true,
                'message' => "Ran {$automation->command} successfully.",
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            Log::error("Automation runNow failed: {$automation->command}", ['error' => $e->getMessage()]);
            $automation->recordRun('failed', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Command failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function toArray(Automation $a): array
    {
        return [
            'id' => $a->id,
            'key' => $a->key,
            'name' => $a->name,
            'command' => $a->command,
            'schedule_preset' => $a->schedule_preset,
            'schedule_params' => $a->schedule_params,
            'cron_expression' => $a->cron_expression,
            'schedule_description' => $a->schedule_description,
            'enabled' => $a->enabled,
            'last_run_at' => $a->last_run_at?->toIso8601String(),
            'last_run_status' => $a->last_run_status,
        ];
    }
}
