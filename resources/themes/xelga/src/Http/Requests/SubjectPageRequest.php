<?php

namespace Themes\Xelga\Http\Requests;

/**
 * GET subject: the storefront page of one record (`type` and `id`), so the
 * editor can open a page type on that record.
 */
class SubjectPageRequest extends EditorRequest
{
    /** Kinds of record a page type is drawn on. */
    public const TYPES = ['product', 'category', 'brand', 'page', 'blog_post'];

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:' . implode(',', self::TYPES)],
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function subjectType(): string
    {
        return (string) $this->validated('type');
    }

    public function subjectId(): int
    {
        return (int) $this->validated('id');
    }
}
