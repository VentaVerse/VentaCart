<?php

namespace App\Models\Concerns;

use App\Support\RichText;

/**
 * A model with rich text fields that themes print as HTML. Name them in
 * richTextFields(); a change by someone without storefront.code is passed
 * through the allow-list in App\Support\RichText before it is stored.
 */
trait HasRichText
{
    public static function bootHasRichText(): void
    {
        static::saving(fn ($model) => RichText::guard($model, $model->richTextFields()));
    }

    /** @return list<string> */
    abstract public function richTextFields(): array;
}
