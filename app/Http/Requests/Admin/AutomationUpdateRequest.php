<?php

namespace App\Http\Requests\Admin;

use App\Services\CronExpressionBuilder;
use Illuminate\Foundation\Http\FormRequest;

class AutomationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin middleware gates the parent route group
    }

    public function rules(): array
    {
        return self::rulesFor($this->input('schedule_preset'));
    }

    /**
     * The schedule rules, keyed under $prefix, so the same rules validate the
     * global Automations page (no prefix) and an extension's own settings
     * form, where the fields ride under automation[<key>][...].
     *
     * @return array<string, array<int, string>>
     */
    public static function rulesFor(?string $preset, string $prefix = ''): array
    {
        $rules = [
            $prefix . 'schedule_preset' => ['required', 'string', 'in:' . implode(',', CronExpressionBuilder::PRESETS)],
            $prefix . 'enabled' => ['sometimes', 'boolean'],
        ];
        $p = $prefix . 'schedule_params.';

        return array_merge($rules, match ($preset) {
            'every_n_minutes' => [
                $p . 'minute' => ['required', 'integer', 'in:' . implode(',', CronExpressionBuilder::ALLOWED_MINUTE_INTERVALS)],
            ],
            'hourly' => [
                $p . 'minute' => ['required', 'integer', 'between:0,59'],
            ],
            'daily' => [
                $p . 'hour' => ['required', 'integer', 'between:0,23'],
                $p . 'minute' => ['required', 'integer', 'between:0,59'],
            ],
            'weekly' => [
                $p . 'day_of_week' => ['required', 'integer', 'between:0,6'],
                $p . 'hour' => ['required', 'integer', 'between:0,23'],
                $p . 'minute' => ['required', 'integer', 'between:0,59'],
            ],
            'monthly' => [
                $p . 'day_of_month' => ['required', 'integer', 'between:1,28'],
                $p . 'hour' => ['required', 'integer', 'between:0,23'],
                $p . 'minute' => ['required', 'integer', 'between:0,59'],
            ],
            default => [],
        });
    }

    public function messages(): array
    {
        return [
            'schedule_params.day_of_month.between' => 'Day of month must be between 1 and 28 (avoids month-end edge cases).',
            'schedule_params.minute.in' => 'Interval must be one of: ' . implode(', ', CronExpressionBuilder::ALLOWED_MINUTE_INTERVALS) . ' minutes.',
        ];
    }
}
