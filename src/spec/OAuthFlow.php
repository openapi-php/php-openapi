<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * Configuration details for a supported OAuth Flow.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#oauthFlowObject
 *
 * @property string $authorizationUrl
 * @property string $tokenUrl
 * @property string $refreshUrl
 * @property array<string> $scopes
 */
final class OAuthFlow extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'authorizationUrl' => Type::STRING,
            'refreshUrl' => Type::STRING,
            'scopes' => [Type::STRING, Type::STRING],
            'tokenUrl' => Type::STRING,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     *
     * Call `addError()` in case of validation errors.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['scopes']);
        // TODO: Validation in context of the parent object
        // authorizationUrl is required if this object is in "implicit", "authorizationCode"
        // tokenUrl is required if this object is in "password", "clientCredentials", "authorizationCode"
    }
}
