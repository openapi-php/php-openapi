<?php

declare(strict_types=1);

namespace openapiphp\openapi\exceptions;

use Exception;

/**
 * This exception is thrown when reading or writing of a file fails.
 */
class IOException extends Exception
{
    /** @var string|null if available, the name of the affected file. */
    public string|null $fileName = null;
}
