<?php

declare(strict_types=1);

namespace openapiphp\openapi\exceptions;

use Exception;
use openapiphp\openapi\json\JsonPointer;

/**
 * This exception is thrown on attempt to resolve a reference which points to a non-existing target.
 */
class UnresolvableReferenceException extends Exception
{
    /**
     * @var JsonPointer|null may contain context information in form of a JSON pointer to the position
     * of the broken reference in the document.
     */
    public JsonPointer|null $context = null;
}
