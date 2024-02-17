<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * A metadata object that allows for more fine-tuned XML model definitions.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#xmlObject
 *
 * @property string $name
 * @property string $namespace
 * @property string $prefix
 * @property bool $attribute
 * @property bool $wrapped
 */
final class Xml extends SpecBaseObject
{
    /** @inheritDoc */
    protected function attributes(): array
    {
        return [
            'attribute' => Type::BOOLEAN,
            'name' => Type::STRING,
            'namespace' => Type::STRING,
            'prefix' => Type::STRING,
            'wrapped' => Type::BOOLEAN,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
    }
}
