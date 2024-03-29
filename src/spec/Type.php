<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use function in_array;

/**
 * Data Types
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#dataTypes
 */
final class Type
{
    public const ANY     = 'any';
    public const INTEGER = 'integer';
    public const NUMBER  = 'number';
    public const STRING  = 'string';
    public const BOOLEAN = 'boolean';
    public const OBJECT  = 'object';
    public const ARRAY   = 'array';

    // Since OpenAPI 3.1
    public const NULL = 'null';

    /**
     * Indicate whether a type is a scalar type, i.e. not an array or object.
     *
     * For ANY this will return false.
     *
     * @param string $type value from one of the type constants defined in this class.
     *
     * @return bool whether the type is a scalar type.
     */
    public static function isScalar(string $type): bool
    {
        return in_array($type, [
            self::INTEGER,
            self::NUMBER,
            self::STRING,
            self::BOOLEAN,
            self::NULL,
        ]);
    }
}
