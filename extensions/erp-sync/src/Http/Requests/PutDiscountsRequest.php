<?php

namespace Extensions\ErpSync\Http\Requests;

use App\Http\Controllers\Api\LogsApiCalls;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The full list of a product's discounts, replacing what is stored.
 *
 * The list is required even when empty: an empty list clears the discounts,
 * while a body without the key is a mistake and must not clear anything.
 * customer_group_id must be sent, as a group id or null for every group, so a
 * row that forgot its group is refused rather than offered to all buyers.
 */
class PutDiscountsRequest extends FormRequest
{
    use LogsApiCalls;

    public const MAX_ROWS = 50;

    /** The erp.auth middleware has already checked the key and its ability. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discounts' => ['present', 'list', 'max:' . self::MAX_ROWS],
            'discounts.*' => ['array'],
            'discounts.*.customer_group_id' => ['present', 'nullable', 'integer', 'exists:customer_groups,id'],
            'discounts.*.min_quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'discounts.*.type' => ['required', 'in:fixed,percent'],
            'discounts.*.value' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ];
    }

    /**
     * What the field rules cannot say alone, checked only on rows whose
     * fields already passed: a percent must take something off and leave
     * something to pay, and two rows for the same group and quantity would
     * leave the price to row order. Null (every group) counts as a group of
     * its own, so it may repeat a named group's quantity but not its own.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $errors = $validator->errors();
            $seen = [];

            foreach ((array) $this->input('discounts', []) as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }

                if (! $errors->has("discounts.{$i}.type") && ! $errors->has("discounts.{$i}.value")
                    && $row['type'] === 'percent' && ((float) $row['value'] <= 0 || (float) $row['value'] >= 100)) {
                    $errors->add("discounts.{$i}.value", 'A percent must be more than 0 and less than 100.');
                }

                if ($errors->has("discounts.{$i}.customer_group_id") || $errors->has("discounts.{$i}.min_quantity")) {
                    continue;
                }

                $key = ($row['customer_group_id'] === null ? 'all' : (int) $row['customer_group_id']) . '|' . (int) $row['min_quantity'];
                if (isset($seen[$key])) {
                    $errors->add("discounts.{$i}.min_quantity", 'Another row already sets this customer group and min_quantity.');
                }
                $seen[$key] = true;
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
}
