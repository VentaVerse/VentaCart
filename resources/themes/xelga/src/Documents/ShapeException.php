<?php

namespace Themes\Xelga\Documents;

/** A document whose structure is wrong; answered as 422 with field paths. */
final class ShapeException extends \RuntimeException
{
    /** @param array<string, list<string>> $errors path => messages */
    public function __construct(public readonly array $errors)
    {
        parent::__construct((string) (array_values($errors)[0][0] ?? 'The page could not be saved.'));
    }
}
