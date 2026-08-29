<?php

namespace App\Services;

use App\Http\Requests\Admin\AutomationUpdateRequest;
use App\Models\Automation;
use Illuminate\Support\Facades\Validator;

/**
 * Saves an automation's switch and schedule from an extension's OWN settings
 * form, so the control can live where the feature lives (owner ruling,
 * 2026-08-30) while the row stays the one the global Automations page and
 * the schedule:run heartbeat read.
 *
 * The form carries the fields under automation[<key>][enabled |
 * schedule_preset | schedule_params][...], which is what x-admin.automation
 * renders. A controller validates the whole request first (its own keys),
 * then hands the request input here for the automation part.
 */
class AutomationInput
{
    /**
     * @param  array<string, mixed>  $input  the request's full input
     * @return Automation|null the row written, or null when the form carried
     *   nothing for this key (the panel was not on the page)
     *
     * @throws \Illuminate\Validation\ValidationException with the errors keyed
     *   automation.<key>.<field>, so they land beside the fields that raised them
     */
    public static function apply(string $key, array $input): ?Automation
    {
        $fields = $input['automation'][$key] ?? null;
        if (! is_array($fields)) {
            return null;
        }

        $automation = Automation::where('key', $key)->first();
        if (! $automation) {
            return null;
        }

        $prefix = "automation.{$key}.";
        Validator::make($input, AutomationUpdateRequest::rulesFor($fields['schedule_preset'] ?? null, $prefix))->validate();

        $preset = (string) $fields['schedule_preset'];
        $params = array_map('intval', (array) ($fields['schedule_params'] ?? []));

        $automation->update([
            'schedule_preset' => $preset,
            'schedule_params' => $params,
            'cron_expression' => CronExpressionBuilder::build($preset, $params),
            'enabled'         => filter_var($fields['enabled'] ?? $automation->enabled, FILTER_VALIDATE_BOOL),
        ]);

        return $automation;
    }
}
