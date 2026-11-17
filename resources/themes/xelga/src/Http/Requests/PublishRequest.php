<?php

namespace Themes\Xelga\Http\Requests;

/** POST publish: every document with a draft and the draft_version the editor last saw. */
class PublishRequest extends EditorRequest
{
    public function rules(): array
    {
        return [
            'versions' => ['present', 'array', 'max:200'],
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
