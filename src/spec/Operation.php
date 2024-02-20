<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * Describes a single API operation on a path.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#operationObject
 *
 * @property string[] $tags
 * @property string $summary
 * @property string $description
 * @property ExternalDocumentation|null $externalDocs
 * @property string $operationId
 * @property Parameter[]|Reference[] $parameters
 * @property RequestBody|Reference|null $requestBody
 * @property Responses|Response[]|null $responses
 * @property Callback[]|Reference[] $callbacks
 * @property bool $deprecated
 * @property SecurityRequirement[] $security
 * @property Server[] $servers
 */
class Operation extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'tags' => [Type::STRING],
            'summary' => Type::STRING,
            'description' => Type::STRING,
            'externalDocs' => ExternalDocumentation::class,
            'operationId' => Type::STRING,
            'parameters' => [Parameter::class],
            'requestBody' => RequestBody::class,
            'responses' => Responses::class,
            'callbacks' => [Type::STRING, Callback::class],
            'deprecated' => Type::BOOLEAN,
            'security' => [SecurityRequirement::class],
            'servers' => [Server::class],
        ];
    }

    /** @inheritDoc */
    protected function attributeDefaults(): array
    {
        return ['security' => null];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     *
     * Call `addError()` in case of validation errors.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['responses']);
    }
}
