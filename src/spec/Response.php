<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * Describes a single response from an API Operation, including design-time, static links to operations based on the response.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#responseObject
 *
 * @property string $description
 * @property Header[]|Reference[] $headers
 * @property MediaType[] $content
 * @property Link[]|Reference[] $links
 */
class Response extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'description' => Type::STRING,
            'headers' => [Type::STRING, Header::class],
            'content' => [Type::STRING, MediaType::class],
            'links' => [Type::STRING, Link::class],
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['description']);
    }
}
