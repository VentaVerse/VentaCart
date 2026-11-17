<?php

namespace Themes\Xelga\Http\Requests;

/**
 * DELETE templates/{handle}: delete a special layout with its assignments.
 * It carries no body; the theme permission (EditorRequest::authorize), the
 * web group's CSRF check and the route's throttle guard it.
 */
class TemplateDestroyRequest extends EditorRequest
{
    public function rules(): array
    {
        return [];
    }
}
