<?php

namespace App\Support;

/**
 * The core's one question about the reviews extension: is it here? The
 * storefront asks before eager-loading rating aggregates or rendering
 * review sections, so a store without the extension simply shows no
 * stars rather than erroring.
 */
class Reviews
{
    public static function enabled(): bool
    {
        return class_exists(\Extensions\Reviews\Models\Review::class);
    }
}
