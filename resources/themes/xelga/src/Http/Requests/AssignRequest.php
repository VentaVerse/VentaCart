<?php

namespace Themes\Xelga\Http\Requests;

use Illuminate\Validation\Rule;
use Themes\Xelga\Documents\Assignment;
use Themes\Xelga\Documents\TemplateManager;

/**
 * PUT assignments: give products (or categories, brands, pages, blog posts)
 * a special layout, or with a null handle take theirs away.
 */
class AssignRequest extends EditorRequest
{
    public function rules(): array
    {
        return [
            'subject_type' => ['required', 'string', Rule::in(Assignment::SUBJECTS)],
            'subject_ids' => ['required', 'array', 'min:1', 'max:' . TemplateManager::MAX_SUBJECTS_PER_CALL],
            'subject_ids.*' => ['integer', 'min:1', 'max:9007199254740991', 'distinct'],
            'handle' => ['present', 'nullable', 'string', 'max:64', 'regex:/\A[a-z0-9][a-z0-9.\-]{0,63}\z/'],
        ];
    }

    /** @return list<int> */
    public function subjectIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('subject_ids')));
    }
}
