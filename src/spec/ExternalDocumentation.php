<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * Allows referencing an external resource for extended documentation.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#externalDocumentationObject
 *
 * @property string $description
 * @property string $url
 */
class ExternalDocumentation extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'description' => Type::STRING,
            'url' => Type::STRING,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['url']);
        $this->validateUrl('url');
    }
}
