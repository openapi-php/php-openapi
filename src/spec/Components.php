<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

use function array_keys;
use function is_array;
use function preg_match;
use function sprintf;

/**
 * Holds a set of reusable objects for different aspects of the OAS.
 *
 * All objects defined within the components object will have no effect on the API unless they are explicitly referenced
 * from properties outside the components object.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#componentsObject
 *
 * @property array<Schema>|array<Reference> $schemas
 * @property array<Response>|array<Reference> $responses
 * @property array<Parameter>|array<Reference> $parameters
 * @property array<Example>|array<Reference> $examples
 * @property array<RequestBody>|array<Reference> $requestBodies
 * @property array<Header>|array<Reference> $headers
 * @property array<SecurityScheme>|array<Reference> $securitySchemes
 * @property array<Link>|array<Reference> $links
 * @property array<Callback>|array<Reference> $callbacks
 */
final class Components extends SpecBaseObject
{
    /** @inheritDoc */
    protected function attributes(): array
    {
        return [
            'callbacks' => [Type::STRING, Callback::class],
            'examples' => [Type::STRING, Example::class],
            'headers' => [Type::STRING, Header::class],
            'links' => [Type::STRING, Link::class],
            'parameters' => [Type::STRING, Parameter::class],
            'requestBodies' => [Type::STRING, RequestBody::class],
            'responses' => [Type::STRING, Response::class],
            'schemas' => [Type::STRING, Schema::class],
            'securitySchemes' => [Type::STRING, SecurityScheme::class],
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        // All the fixed fields declared above are objects that MUST use keys that match the regular expression: ^[a-zA-Z0-9\.\-_]+$.
        foreach (array_keys($this->attributes()) as $attribute) {
            if (! is_array($this->$attribute)) {
                continue;
            }

            foreach (array_keys($this->$attribute) as $k) {
                if (preg_match('~^[a-zA-Z0-9\.\-_]+$~', (string) $k)) {
                    continue;
                }

                $this->addError(
                    sprintf(
                        'Invalid key \'%s\' used in Components Object for attribute \'%s\', does not match ^[a-zA-Z0-9\.\-_]+$.',
                        $k,
                        $attribute,
                    ),
                );
            }
        }
    }
}
