<?php

namespace Extensions\ErpSync\Http\Requests;

use App\Http\Controllers\Api\LogsApiCalls;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

/**
 * The full list of a product's special prices, replacing what is stored.
 *
 * The list is required even when empty: an empty list clears the specials,
 * while a body without the key is a mistake and must not clear anything.
 * Times must name their offset, because a time without one could be read in
 * the wrong zone and start or end a sale hours off.
 */
class PutSpecialsRequest extends FormRequest
{
    use LogsApiCalls;

    /** ISO 8601 date and time with an offset (Z or +08:00). */
    public const DATETIME_WITH_OFFSET = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/';

    public const MAX_ROWS = 50;

    /** The erp.auth middleware has already checked the key and its ability. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'specials' => ['present', 'list', 'max:' . self::MAX_ROWS],
            'specials.*' => ['array'],
            'specials.*.type' => ['required', 'in:fixed,percent'],
            'specials.*.value' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'specials.*.starts_at' => ['nullable', 'string', 'regex:' . self::DATETIME_WITH_OFFSET],
            'specials.*.ends_at' => ['required', 'string', 'regex:' . self::DATETIME_WITH_OFFSET],
        ];
    }

    public function messages(): array
    {
        return [
            'specials.*.starts_at.regex' => 'starts_at must be an ISO 8601 date and time with an offset, such as 2026-11-28T00:00:00+08:00.',
            'specials.*.ends_at.regex' => 'ends_at must be an ISO 8601 date and time with an offset, such as 2026-11-30T23:59:59+08:00.',
        ];
    }

    /**
     * What the field rules cannot say alone, checked only on rows whose
     * fields already passed: a percent must take something off and leave
     * something to pay, and a sale must end after it starts.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $errors = $validator->errors();

            foreach ((array) $this->input('specials', []) as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }

                if (! $errors->has("specials.{$i}.type") && ! $errors->has("specials.{$i}.value")
                    && $row['type'] === 'percent' && ((float) $row['value'] <= 0 || (float) $row['value'] >= 100)) {
                    $errors->add("specials.{$i}.value", 'A percent must be more than 0 and less than 100.');
                }

                $start = $this->moment($row['starts_at'] ?? null);
                $end = $this->moment($row['ends_at'] ?? null);

                foreach (['starts_at' => $start, 'ends_at' => $end] as $field => $moment) {
                    if ($moment === false && ! $errors->has("specials.{$i}.{$field}")) {
                        $errors->add("specials.{$i}.{$field}", "{$field} is not a real date and time.");
                    }
                }

                if ($start instanceof Carbon && $end instanceof Carbon && $end->lessThanOrEqualTo($start)) {
                    $errors->add("specials.{$i}.ends_at", 'ends_at must be after starts_at.');
                }
            }
        }];
    }

    /** Refusals are logged like every other ERP call. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException($this->logAndRespond($this, [
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422, $this->route('sku')));
    }

    /**
     * The moment a value names, null when there is none, or false when it is
     * not one (wrong shape, or a date such as February 30).
     */
    private function moment(mixed $value): Carbon|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! preg_match(self::DATETIME_WITH_OFFSET, $value)) {
            return false;
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (\Throwable) {
            return false;
        }

        // Carbon rolls an impossible day over to the next month; a real
        // date reads back the same.
        return $parsed->format('Y-m-d') === substr($value, 0, 10) ? $parsed : false;
    }
}
