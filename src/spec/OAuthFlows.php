<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * Allows configuration of the supported OAuth Flows.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#oauthFlowsObject
 *
 * @property OAuthFlow|null $implicit
 * @property OAuthFlow|null $password
 * @property OAuthFlow|null $clientCredentials
 * @property OAuthFlow|null $authorizationCode
 */
class OAuthFlows extends SpecBaseObject
{
    /** @inheritDoc */
    protected function attributes(): array
    {
        return [
            'implicit' => OAuthFlow::class,
            'password' => OAuthFlow::class,
            'clientCredentials' => OAuthFlow::class,
            'authorizationCode' => OAuthFlow::class,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
    }
}
