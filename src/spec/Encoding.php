<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * A single encoding definition applied to a single schema property.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#encodingObject
 *
 * @property string $contentType
 * @property Header[]|Reference[] $headers
 * @property string $style
 * @property bool $explode
 * @property bool $allowReserved
 */
class Encoding extends SpecBaseObject
{
    /** @inheritDoc */
    protected function attributes(): array
    {
        return [
            'contentType' => Type::STRING,
            'headers' => [Type::STRING, Header::class],
            // TODO implement default values for style
            // https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#encodingObject
            'style' => Type::STRING,
            'explode' => Type::BOOLEAN,
            'allowReserved' => Type::BOOLEAN,
        ];
    }

    /** @var array<string, string|bool> */
    private array $_attributeDefaults = [];

    /** @inheritDoc */
    protected function attributeDefaults(): array
    {
        return $this->_attributeDefaults;
    }

    /** @inheritDoc */
    public function __construct(array $data, Schema|null $schema = null)
    {
        if (isset($data['style'])) {
            // Spec: When style is form, the default value is true.
            $this->_attributeDefaults['explode'] = ($data['style'] === 'form');
        }

        if ($schema instanceof Schema) {
            // Spec: Default value depends on the property type:
            // for string with format being binary – application/octet-stream;
            // for other primitive types – text/plain;
            // for object - application/json;
            // for array – the default is defined based on the inner type.
            switch ($schema->type === 'array' ? ($schema->items->type ?? 'array') : $schema->type) {
                case Type::STRING:
                    if ($schema->format === 'binary') {
                        $this->_attributeDefaults['contentType'] = 'application/octet-stream';
                        break;
                    }
                    // no break here
                case Type::BOOLEAN:
                case Type::INTEGER:
                case Type::NUMBER:
                    $this->_attributeDefaults['contentType'] = 'text/plain';
                    break;
                case 'object':
                    $this->_attributeDefaults['contentType'] = 'application/json';
                    break;
            }
        }

        parent::__construct($data);
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
    }
}
