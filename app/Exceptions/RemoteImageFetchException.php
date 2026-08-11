<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside an ERP push transaction when one or more caller-supplied
 * image URLs could not be fetched.
 *
 * It exists purely to unwind the surrounding DB::transaction so the push is
 * rolled back whole. The controller catches it, cleans up the files that
 * request wrote, and answers 422 with the per-url failure list it carries.
 */
class RemoteImageFetchException extends RuntimeException
{
    /**
     * @param  array<int, array{url: string, reason: string}>  $failures
     * @param  int  $attempted  how many urls the caller offered in total
     */
    public function __construct(
        public readonly array $failures,
        public readonly int $attempted
    ) {
        parent::__construct('One or more images could not be fetched.');
    }
}
