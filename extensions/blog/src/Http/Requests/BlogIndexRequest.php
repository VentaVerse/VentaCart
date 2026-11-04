<?php

namespace Extensions\Blog\Http\Requests;

use Extensions\Blog\Models\BlogPost;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The blog listing's filters: a text search, a category, a tag and the
 * grid or list view. Anyone may read the blog.
 */
class BlogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('q'))) {
            $q = trim((string) preg_replace('/\s+/u', ' ', $this->query('q')));
            $this->merge(['q' => $q === '' ? null : $q]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:' . BlogPost::SEARCH_MIN, 'max:' . BlogPost::SEARCH_MAX],
            'category' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'view' => ['nullable', 'string', 'max:20'],
            // The redirect below keeps only values that pass these same limits.
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'Enter words to search for.',
            'q.min' => 'Enter at least :min characters to search.',
            'q.max' => 'Keep the search under :max characters.',
        ];
    }

    /**
     * A failed search goes back to the blog with the other filters kept,
     * never to the same address, so a bad link cannot redirect in a loop.
     */
    protected function getRedirectUrl(): string
    {
        $keep = [];
        foreach (['category' => 255, 'tag' => 255, 'view' => 20] as $key => $max) {
            $value = $this->query($key);
            if (is_string($value) && $value !== '' && mb_strlen($value) <= $max) {
                $keep[$key] = $value;
            }
        }

        return route('blog.index', $keep);
    }

    /** The search, trimmed, or null when there is none. */
    public function search(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) && $q !== '' ? $q : null;
    }
}
