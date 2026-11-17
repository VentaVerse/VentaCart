<?php

namespace Themes\Xelga\Http\Requests;

use Illuminate\Validation\Rule;
use Themes\Xelga\StoreTemplates\StoreTemplateApplier;

/**
 * POST store-templates/{slug}/apply: what to bring from the store (the look
 * and home page, or the look only) and the draft_version of every document
 * it replaces, as the editor last saw them.
 */
class StoreTemplateApplyRequest extends EditorRequest
{
    public function rules(): array
    {
        return [
            'bring' => ['required', 'string', Rule::in(StoreTemplateApplier::BRING)],
            'versions' => ['required', 'array', 'max:8'],
            'versions.*' => ['integer', 'min:0', 'max:4294967295'],
        ];
    }

    /** @return array<string, int> */
    public function versions(): array
    {
        $out = [];
        foreach ((array) $this->validated('versions', []) as $handle => $version) {
            $out[(string) $handle] = (int) $version;
        }

        return $out;
    }
}
