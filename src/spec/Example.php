<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * Example Object
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#exampleObject
 *
 * @property string $summary
 * @property string $description
 * @property mixed $value
 * @property string $externalValue
 */
final class Example extends SpecBaseObject
{
    /** @inheritDoc */
    protected function attributes(): array
    {
        return [
            'description' => Type::STRING,
            'externalValue' => Type::STRING,
            'summary' => Type::STRING,
            'value' => Type::ANY,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
    }
}
