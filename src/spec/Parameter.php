<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\OpenApiVersion;
use openapiphp\openapi\SpecBaseObject;

use function count;
use function in_array;

/**
 * Describes a single operation parameter.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#parameterObject
 *
 * @property string $name
 * @property string $in
 * @property string $description
 * @property bool $required
 * @property bool $deprecated
 * @property bool $allowEmptyValue
 *
 * @property string $style
 * @property bool $explode
 * @property bool $allowReserved
 * @property Schema|Reference|null $schema
 * @property mixed $example
 * @property Example[] $examples
 *
 * @property MediaType[] $content
 */
class Parameter extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            'name' => Type::STRING,
            'in' => Type::STRING,
            'description' => Type::STRING,
            'required' => Type::BOOLEAN,
            'deprecated' => Type::BOOLEAN,
            'allowEmptyValue' => Type::BOOLEAN,

            'style' => Type::STRING,
            'explode' => Type::BOOLEAN,
            'allowReserved' => Type::BOOLEAN,
            'schema' => Schema::class,
            'example' => Type::ANY,
            'examples' => [Type::STRING, Example::class],

            'content' => [Type::STRING, MediaType::class],
        ];
    }

    /** @var array<string, string|bool>  */
    private array $_attributeDefaults = [];

    /** @inheritDoc */
    protected function attributeDefaults(): array
    {
        return $this->_attributeDefaults;
    }

    /** @inheritDoc */
    public function __construct(array $data, OpenApiVersion|null $openApiVersion = null)
    {
        if (isset($data['in'])) {
            // Spec: Default values (based on value of in):
            // for query - form;
            // for path - simple;
            // for header - simple;
            // for cookie - form.
            switch ($data['in']) {
                case 'query':
                case 'cookie':
                    $this->_attributeDefaults['style']   = 'form';
                    $this->_attributeDefaults['explode'] = true;
                    break;
                case 'path':
                case 'header':
                    $this->_attributeDefaults['style']   = 'simple';
                    $this->_attributeDefaults['explode'] = false;
                    break;
            }
        }

        if (isset($data['style'])) {
            // Spec: When style is form, the default value is true. For all other styles, the default value is false.
            $this->_attributeDefaults['explode'] = ($data['style'] === 'form');
        }

        parent::__construct($data, $openApiVersion);
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     *
     * Call `addError()` in case of validation errors.
     */
    protected function performValidation(): void
    {
        $this->requireProperties(['name', 'in']);
        if ($this->in === 'path') {
            $this->requireProperties(['required']);
            if (! $this->required) {
                $this->addError("Parameter 'required' must be true for 'in': 'path'.");
            }
        }

        if (! empty($this->content) && ! empty($this->schema)) {
            $this->addError('A Parameter Object MUST contain either a schema property, or a content property, but not both.');
        }

        if (! empty($this->content) && count($this->content) !== 1) {
            $this->addError('A Parameter Object with Content property MUST have A SINGLE content type.');
        }

        $supportedSerializationStyles = [
            'path' => ['simple', 'label', 'matrix'],
            'query' => ['form', 'spaceDelimited', 'pipeDelimited', 'deepObject'],
            'header' => ['simple'],
            'cookie' => ['form'],
        ];
        if (! isset($supportedSerializationStyles[$this->in]) || in_array($this->style, $supportedSerializationStyles[$this->in])) {
            return;
        }

        $this->addError('A Parameter Object DOES NOT support this serialization style.');
    }
}
