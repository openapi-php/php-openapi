<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

use function in_array;

/**
 * Defines a security scheme that can be used by the operations.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#securitySchemeObject
 *
 * @property string $type
 * @property string $description
 * @property string $name
 * @property string $in
 * @property string $scheme
 * @property string $bearerFormat
 * @property OAuthFlows|null $flows
 * @property string $openIdConnectUrl
 */
final class SecurityScheme extends SpecBaseObject
{
    /** @var list<string> */
    private array $knownTypes = [
        'apiKey',
        'http',
        'oauth2',
        'openIdConnect',
    ];

    /** @inheritDoc */
    protected function attributes(): array
    {
        return [
            'bearerFormat' => Type::STRING,
            'description' => Type::STRING,
            'flows' => OAuthFlows::class,
            'in' => Type::STRING,
            'name' => Type::STRING,
            'openIdConnectUrl' => Type::STRING,
            'scheme' => Type::STRING,
            'type' => Type::STRING,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['type']);
        if ($this->type === null) {
            return;
        }

        if (! in_array($this->type, $this->knownTypes)) {
            $this->addError('Unknown Security Scheme type: ' . $this->type);
        } else {
            switch ($this->type) {
                case 'apiKey':
                    $this->requireProperties(['name', 'in']);
                    if ($this->in !== null && ! in_array($this->in, ['query', 'header', 'cookie'])) {
                        $this->addError('Invalid value for Security Scheme property \'in\': ' . $this->in);
                    }

                    break;
                case 'http':
                    $this->requireProperties(['scheme']);
                    break;
                case 'oauth2':
                    $this->requireProperties(['flows']);
                    break;
                case 'openIdConnect':
                    $this->requireProperties(['openIdConnectUrl']);
                    break;
            }
        }
    }
}
