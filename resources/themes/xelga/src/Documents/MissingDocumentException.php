<?php

namespace Themes\Xelga\Documents;

/** The document was deleted (a special layout someone removed); answered as 404. */
final class MissingDocumentException extends \RuntimeException
{
    public function __construct(string $message = 'This layout has been deleted.')
    {
        parent::__construct($message);
    }
}
