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
        $preset = $this->input('schedule_preset');

        $rules = [
            'schedule_preset' => ['required', 'string', 'in:' . implode(',', CronExpressionBuilder::PRESETS)],
            'enabled' => ['sometimes', 'boolean'],
        ];

        $rules = array_merge($rules, match ($preset) {
            'every_n_minutes' => [
                'schedule_params.minute' => ['required', 'integer', 'in:' . implode(',', CronExpressionBuilder::ALLOWED_MINUTE_INTERVALS)],
            ],
            'hourly' => [
                'schedule_params.minute' => ['required', 'integer', 'between:0,59'],
            ],
            'daily' => [
                'schedule_params.hour' => ['required', 'integer', 'between:0,23'],
                'schedule_params.minute' => ['required', 'integer', 'between:0,59'],
            ],
            'weekly' => [
                'schedule_params.day_of_week' => ['required', 'integer', 'between:0,6'],
                'schedule_params.hour' => ['required', 'integer', 'between:0,23'],
                'schedule_params.minute' => ['required', 'integer', 'between:0,59'],
            ],
            'monthly' => [
                'schedule_params.day_of_month' => ['required', 'integer', 'between:1,28'],
                'schedule_params.hour' => ['required', 'integer', 'between:0,23'],
                'schedule_params.minute' => ['required', 'integer', 'between:0,59'],
            ],
            default => [],
        });

        return $rules;
    }

    public function messages(): array
    {
        return [
            'schedule_params.day_of_month.between' => 'Day of month must be between 1 and 28 (avoids month-end edge cases).',
            'schedule_params.minute.in' => 'Interval must be one of: ' . implode(', ', CronExpressionBuilder::ALLOWED_MINUTE_INTERVALS) . ' minutes.',
        ];
    }
}
