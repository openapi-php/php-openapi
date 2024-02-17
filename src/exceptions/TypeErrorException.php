<?php

declare(strict_types=1);

namespace openapiphp\openapi\exceptions;

use Exception;

/**
 * This exception is thrown if the input data from OpenAPI spec
 * provides data in another type that is expected.
 */
class TypeErrorException extends Exception
{
}
