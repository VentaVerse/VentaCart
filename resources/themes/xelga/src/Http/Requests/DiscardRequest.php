<?php

namespace Themes\Xelga\Http\Requests;

/** DELETE documents/draft: throw every draft away; with versions, only if none moved on. */
class DiscardRequest extends EditorRequest
{
    public function rules(): array
    {
        return [
            'versions' => ['sometimes', 'array', 'max:200'],
            'versions.*' => ['integer', 'min:0', 'max:4294967295'],
        ];
    }

    /** @return array<string, int>|null */
    public function versions(): ?array
    {
        if (! $this->has('versions')) {
            return null;
        }
        $out = [];
        foreach ((array) $this->validated('versions', []) as $handle => $version) {
            $out[(string) $handle] = (int) $version;
        }

        return $out;
    }
}
