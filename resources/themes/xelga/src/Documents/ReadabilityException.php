<?php

namespace Themes\Xelga\Documents;

/** Publish refused because a color scheme's text would be hard to read. */
final class ReadabilityException extends \RuntimeException
{
    /** @param list<array> $failures from ContrastCheck::failures() */
    public function __construct(public readonly array $failures)
    {
        $names = array_values(array_unique(array_column($failures, 'label')));
        $more = count($failures) > 1 ? ' Also check: ' . implode(', ', $names) . '.' : '';
        parent::__construct('Publish is waiting on readable colors. ' . ($failures[0]['message'] ?? '') . $more);
    }
}
