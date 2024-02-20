<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\exceptions\TypeErrorException;
use openapiphp\openapi\OpenApiVersion;
use openapiphp\openapi\SpecBaseObject;

use function gettype;
use function is_array;
use function is_object;
use function sprintf;

/**
 * Each Media Type Object provides schema and examples for the media type identified by its key.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#mediaTypeObject
 *
 * @property Schema|Reference|null $schema
 * @property mixed $example
 * @property Example[]|Reference[] $examples
 * @property Encoding[] $encoding
 */
class MediaType extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'schema' => Schema::class,
            'example' => Type::ANY,
            'examples' => [Type::STRING, Example::class],
            'encoding' => [Type::STRING, Encoding::class],
        ];
    }

    /** @inheritDoc */
    public function __construct(array $data, OpenApiVersion|null $openApiVersion = null)
    {
        // instantiate Encoding by passing the schema for extracting default values
        $encoding = $data['encoding'] ?? null;
        unset($data['encoding']);

        parent::__construct($data, $openApiVersion);

        if (! is_array($encoding)) {
            return;
        }

        foreach ($encoding as $property => $encodingData) {
            if ($encodingData instanceof Encoding) {
                $encoding[$property] = $encodingData;
            } elseif (is_array($encodingData)) {
                $schema = $this->schema->properties[$property] ?? null;
                // Don't pass the schema if it's still an unresolved reference.
                if ($schema instanceof Reference) {
                    $encoding[$property] = new Encoding($encodingData, $openApiVersion);
                } else {
                    $encoding[$property] = new Encoding($encodingData, $openApiVersion, $schema);
                }
            } else {
                $givenType = gettype($encodingData);
                if ($givenType === 'object' && is_object($encodingData)) {
                    $givenType = $encodingData::class;
                }

                throw new TypeErrorException(sprintf('Encoding MUST be either array or Encoding object, "%s" given', $givenType));
            }
        }

        $this->encoding = $encoding;
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
    }
}
