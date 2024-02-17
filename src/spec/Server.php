<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * An object representing a Server.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#serverObject
 *
 * @property string $url
 * @property string $description
 * @property ServerVariable[] $variables
 */
class Server extends SpecBaseObject
{
    /** @inheritDoc */
    protected function attributes(): array
    {
        return [
            'url' => Type::STRING,
            'description' => Type::STRING,
            'variables' => [Type::STRING, ServerVariable::class],
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['url']);
    }
}
