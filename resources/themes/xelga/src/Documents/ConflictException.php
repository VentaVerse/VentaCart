<?php

namespace Themes\Xelga\Documents;

/**
 * Someone saved or published after the version this change was based on.
 * Answered as 409 with what is there now, so the editor can offer to load
 * it or replace it.
 */
final class ConflictException extends \RuntimeException
{
    public function __construct(string $message, public readonly array $current)
    {
        parent::__construct($message);
    }
}
