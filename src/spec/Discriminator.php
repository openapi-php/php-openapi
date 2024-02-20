<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * When request bodies or response payloads may be one of a number of different schemas, a discriminator object can be used to aid in serialization, deserialization, and validation.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#discriminatorObject
 *
 * @property string $propertyName
 * @property array<string> $mapping
 */
final class Discriminator extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'mapping' => [Type::STRING, Type::STRING],
            'propertyName' => Type::STRING,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['propertyName']);
    }
}
