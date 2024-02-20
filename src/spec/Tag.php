<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * Adds metadata to a single tag that is used by the Operation Object.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#tagObject
 *
 * @property string $name
 * @property string $description
 * @property ExternalDocumentation|null $externalDocs
 */
class Tag extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'name' => Type::STRING,
            'description' => Type::STRING,
            'externalDocs' => ExternalDocumentation::class,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['name']);
    }
}
