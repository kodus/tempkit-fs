<?php

namespace Kodus\TempKit;

use RuntimeException;

/**
 * This exception represents failure to recover a previously collected uploaded file.
 *
 * @see TempFileService::recover()
 */
class TempFileRecoveryException extends RuntimeException
{
    public function __construct(string $uuid)
    {
        parent::__construct("unable to recover temporary file with UUID: {$uuid}");
    }
}
