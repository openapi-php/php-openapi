<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\exceptions\TypeErrorException;
use openapiphp\openapi\OpenApiVersion;
use openapiphp\openapi\SpecBaseObject;

use function gettype;
use function is_array;
use function is_bool;
use function is_object;
use function sprintf;

/**
 * The Schema Object allows the definition of input and output data types.
 *
 * These types can be objects, but also primitives and arrays. This object is an extended subset of the
 * [JSON Schema Specification Wright Draft 00](http://json-schema.org/).
 *
 * For more information about the properties, see
 * [JSON Schema Core](https://tools.ietf.org/html/draft-wright-json-schema-00) and
 * [JSON Schema Validation](https://tools.ietf.org/html/draft-wright-json-schema-validation-00).
 * Unless stated otherwise, the property definitions follow the JSON Schema.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#schemaObject
 *
 * @property string $title
 * @property int|float $multipleOf
 * @property int|float $maximum
 * @property bool $exclusiveMaximum
 * @property int|float $minimum
 * @property bool $exclusiveMinimum
 * @property int $maxLength
 * @property int $minLength
 * @property string $pattern (This string SHOULD be a valid regular expression, according to the [ECMA 262 regular expression dialect](https://www.ecma-international.org/ecma-262/5.1/#sec-7.8.5))
 * @property int $maxItems
 * @property int $minItems
 * @property bool $uniqueItems
 * @property int $maxProperties
 * @property int $minProperties
 * @property string[] $required list of required properties
 * @property array $enum
 *
 * @property string|string[] $type type can only be `string` in OpenAPI 3.0, but can be an array of strings since OpenAPI 3.1
 * @property Schema[]|Reference[] $allOf
 * @property Schema[]|Reference[] $oneOf
 * @property Schema[]|Reference[] $anyOf
 * @property Schema|Reference|null $not
 * @property Schema|Reference|null $items
 * @property Schema[]|Reference[] $properties
 * @property Schema|Reference|bool $additionalProperties
 * @property string $description
 * @property string $format
 * @property mixed $default
 *
 * @property bool $nullable
 * @property Discriminator|null $discriminator
 * @property bool $readOnly
 * @property bool $writeOnly
 * @property Xml|null $xml
 * @property ExternalDocumentation|null $externalDocs
 * @property mixed $example
 * @property bool $deprecated
 */
class Schema extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            // The following properties are taken directly from the JSON Schema definition and follow the same specifications:
            // types from https://tools.ietf.org/html/draft-wright-json-schema-validation-00#section-4 ff.
            'title' => Type::STRING,
            'multipleOf' => Type::NUMBER,
            'maximum' => Type::NUMBER,
            'exclusiveMaximum' => Type::BOOLEAN,
            'minimum' => Type::NUMBER,
            'exclusiveMinimum' => Type::BOOLEAN,
            'maxLength' => Type::INTEGER,
            'minLength' => Type::INTEGER,
            'pattern' => Type::STRING,
            'maxItems' => Type::INTEGER,
            'minItems' => Type::INTEGER,
            'uniqueItems' => Type::BOOLEAN,
            'maxProperties' => Type::INTEGER,
            'minProperties' => Type::INTEGER,
            'required' => [Type::STRING],
            'enum' => [Type::ANY],
            // The following properties are taken from the JSON Schema definition but their definitions were adjusted to the OpenAPI Specification.
            'type' => Type::STRING,
            'allOf' => [self::class],
            'oneOf' => [self::class],
            'anyOf' => [self::class],
            'not' => self::class,
            'items' => self::class,
            'properties' => [Type::STRING, self::class],
            //'additionalProperties' => 'boolean' | ['string', Schema::class], handled in constructor
            'description' => Type::STRING,
            'format' => Type::STRING,
            'default' => Type::ANY,
            // Other than the JSON Schema subset fields, the following fields MAY be used for further schema documentation:
            'nullable' => Type::BOOLEAN,
            'discriminator' => Discriminator::class,
            'readOnly' => Type::BOOLEAN,
            'writeOnly' => Type::BOOLEAN,
            'xml' => Xml::class,
            'externalDocs' => ExternalDocumentation::class,
            'example' => Type::ANY,
            'deprecated' => Type::BOOLEAN,
        ];
    }

    /** @inheritDoc */
    protected function attributeDefaults(): array
    {
        return [
            'additionalProperties' => true,
            'required' => null,
            'enum' => null,
            'allOf' => null,
            'oneOf' => null,
            'anyOf' => null,
            // nullable is only relevant, when a type is specified
            // return null as default when there is no type
            // return false as default when there is a type
            'nullable' => $this->hasPropertyValue('type') ? false : null,
            'exclusiveMinimum' => $this->hasPropertyValue('minimum') ? false : null,
            'exclusiveMaximum' => $this->hasPropertyValue('maximum') ? false : null,
        ];
    }

    /** @inheritDoc */
    public function __construct(array $data, OpenApiVersion|null $openApiVersion = null)
    {
        if (isset($data['additionalProperties'])) {
            if (is_array($data['additionalProperties'])) {
                $data['additionalProperties'] = $this->instantiate(self::class, $data['additionalProperties'], $openApiVersion);
            } elseif (! ($data['additionalProperties'] instanceof Schema || $data['additionalProperties'] instanceof Reference || is_bool($data['additionalProperties']))) {
                $givenType = gettype($data['additionalProperties']);
                if ($givenType === 'object' && is_object($data['additionalProperties'])) {
                    $givenType = $data['additionalProperties']::class;
                }

                throw new TypeErrorException(sprintf('Schema::$additionalProperties MUST be either boolean or a Schema/Reference object, "%s" given', $givenType));
            }
        }

        parent::__construct($data, $openApiVersion);
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
    }
}
