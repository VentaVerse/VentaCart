<?php

namespace Themes\Xelga\Http\Requests;

/** A read from the editor (a document, its revisions): the permission check alone. */
class ReadRequest extends EditorRequest
{
    public function rules(): array
    {
        return [];
    }
}
