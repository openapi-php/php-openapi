<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * Describes a single request body.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#requestBodyObject
 *
 * @property string $description
 * @property MediaType[] $content
 * @property bool $required
 */
class RequestBody extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'description' => Type::STRING,
            'content' => [Type::STRING, MediaType::class],
            'required' => Type::BOOLEAN,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['content']);
    }
}
