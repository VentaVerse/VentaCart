<?php

namespace Themes\Xelga\Http\Requests;

/**
 * GET pick/{kind}: a read-only lookup for a picker. Either a search (`q`)
 * or the names of ids already chosen (`ids`, comma separated; for links
 * each one is type:id).
 */
class PickerRequest extends EditorRequest
{
    public const MAX_IDS = 48;

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'],
            'ids' => ['nullable', 'string', 'max:1200', 'regex:/^([a-z_]{1,16}:)?[1-9][0-9]{0,17}(,([a-z_]{1,16}:)?[1-9][0-9]{0,17})*$/'],
        ];
    }

    public function messages(): array
    {
        return ['ids.regex' => 'The ids must be whole numbers separated by commas.'];
    }

    public function search(): string
    {
        return trim((string) $this->validated('q', ''));
    }

    /**
     * The ids asked for, in order, without repeats; for links each one is
     * [type, id]. Null when the request is a search.
     *
     * @return list<int>|list<array{0: string, 1: int}>|null
     */
    public function ids(bool $typed = false): ?array
    {
        $raw = (string) $this->validated('ids', '');
        if ($raw === '') {
            return null;
        }

        $out = [];
        foreach (array_unique(explode(',', $raw)) as $part) {
            if ($typed) {
                [$type, $id] = array_pad(explode(':', $part, 2), 2, '');
                if ($id !== '') {
                    $out[] = [$type, (int) $id];
                }
            } elseif (! str_contains($part, ':')) {
                $out[] = (int) $part;
            }
        }

        return array_slice($out, 0, self::MAX_IDS);
    }
}
