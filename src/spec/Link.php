<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * The Link object represents a possible design-time link for a response.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#linkObject
 *
 * @property string $operationRef
 * @property string $operationId
 * @property array $parameters
 * @property mixed $requestBody
 * @property string $description
 * @property Server|null $server
 */
class Link extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'operationRef' => Type::STRING,
            'operationId' => Type::STRING,
            'parameters' => [Type::STRING, Type::ANY], // TODO: how to specify {expression}?
            'requestBody' => Type::ANY, // TODO: how to specify {expression}?
            'description' => Type::STRING,
            'server' => Server::class,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        if (empty($this->operationId) || empty($this->operationRef)) {
            return;
        }

        $this->addError('Link: operationId and operationRef are mutually exclusive.');
    }
}
