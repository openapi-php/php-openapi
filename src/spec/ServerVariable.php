<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * An object representing a Server Variable for server URL template substitution.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#serverVariableObject
 *
 * @property array<string> $enum
 * @property string $default
 * @property string $description
 */
final class ServerVariable extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'default' => Type::STRING,
            'description' => Type::STRING,
            'enum' => [Type::STRING],
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['default']);
    }
}
