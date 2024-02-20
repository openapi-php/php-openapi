<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * Contact information for the exposed API.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#contactObject
 *
 * @property string $name
 * @property string $url
 * @property string $email
 */
class Contact extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'name' => Type::STRING,
            'url' => Type::STRING,
            'email' => Type::STRING,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        $this->validateEmail('email');
        $this->validateUrl('url');
    }
}
