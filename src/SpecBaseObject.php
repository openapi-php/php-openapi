<?php

declare(strict_types=1);

namespace openapiphp\openapi;

use openapiphp\openapi\exceptions\TypeErrorException;
use openapiphp\openapi\exceptions\UnknownPropertyException;
use openapiphp\openapi\exceptions\UnresolvableReferenceException;
use openapiphp\openapi\json\JsonPointer;
use openapiphp\openapi\json\JsonReference;
use openapiphp\openapi\spec\Reference;
use openapiphp\openapi\spec\Type;
use TypeError;

use function array_key_exists;
use function array_map;
use function array_merge;
use function count;
use function end;
use function explode;
use function gettype;
use function implode;
use function is_array;
use function is_bool;
use function is_string;
use function print_r;
use function sprintf;
use function str_contains;
use function str_starts_with;

/**
 * Base class for all spec objects.
 *
 * Implements property management and validation basics.
 */
abstract class SpecBaseObject implements SpecObjectInterface, DocumentContextInterface
{
    /** @var array<string,mixed> */
    private array $_properties = [];
    /** @var list<string> */
    private array $_errors = [];

    private bool $_recursingSerializableData = false;
    private bool $_recursingValidate         = false;
    private bool $_recursingErrors           = false;
    private bool $_recursingReferences       = false;
    private bool $_recursingReferenceContext = false;
    private bool $_recursingDocumentContext  = false;

    private SpecObjectInterface|null $_baseDocument = null;
    private JsonPointer|null $_jsonPointer          = null;

    /** @return array<string, string|list<string>> array of attributes available in this object. */
    abstract protected function attributes(): array;

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     *
     * Call `addError()` in case of validation errors.
     */
    abstract protected function performValidation(): void;

    /** @inheritDoc */
    public function __construct(array $data)
    {
        foreach ($this->attributes() as $property => $type) {
            if (! isset($data[$property])) {
                continue;
            }

            if ($type === Type::BOOLEAN) {
                if (! is_bool($data[$property])) {
                    $this->_errors[] = sprintf('property \'%s\' must be boolean, but ', $property) . gettype(
                        $data[$property],
                    ) . ' given.';
                    continue;
                }

                $this->_properties[$property] = $data[$property];
            } elseif (is_array($type)) {
                if (! is_array($data[$property])) {
                    $this->_errors[] = sprintf('property \'%s\' must be array, but ', $property) . gettype(
                        $data[$property],
                    ) . ' given.';
                    continue;
                }

                switch (count($type)) {
                    case 1:
                        if (isset($data[$property]['$ref'])) {
                            $this->_properties[$property] = new Reference($data[$property], null);
                        } else {
                            // array
                            $this->_properties[$property] = [];
                            foreach ($data[$property] as $key => $item) {
                                if ($type[0] === Type::STRING) {
                                    if (! is_string($item)) {
                                        $this->_errors[] = sprintf(
                                            "property '%s' must be array of strings, but array has ",
                                            $property,
                                        ) . gettype($item) . ' element.';
                                    }

                                    $this->_properties[$property][$key] = $item;
                                } elseif (Type::isScalar($type[0])) {
                                    $this->_properties[$property][$key] = $item;
                                } elseif ($type[0] === Type::ANY) {
                                    $this->_properties[$property][$key] = is_array($item) && isset($item['$ref'])
                                        ? new Reference($item, null)
                                        : $item;
                                } else {
                                    $this->_properties[$property][$key] = $this->instantiate($type[0], $item);
                                }
                            }
                        }

                        break;
                    case 2:
                        // map
                        if ($type[0] !== Type::STRING) {
                            throw new TypeErrorException(
                                'Invalid map key type: ' . $type[0],
                            );
                        }

                        $this->_properties[$property] = [];
                        foreach ($data[$property] as $key => $item) {
                            if ($type[1] === Type::STRING) {
                                if (! is_string($item)) {
                                    $this->_errors[] = sprintf(
                                        'property \'%s\' must be map<string, string>, but entry \'%s\' is of type ',
                                        $property,
                                        $key,
                                    ) . gettype($item) . '.';
                                }

                                $this->_properties[$property][$key] = $item;
                            } elseif ($type[1] === Type::ANY || Type::isScalar($type[1])) {
                                $this->_properties[$property][$key] = $item;
                            } else {
                                $this->_properties[$property][$key] = $this->instantiate($type[1], $item);
                            }
                        }

                        break;
                }
            } elseif (Type::isScalar($type)) {
                $this->_properties[$property] = $data[$property];
            } elseif ($type === Type::ANY) {
                $this->_properties[$property] = is_array($data[$property]) && isset($data[$property]['$ref'])
                    ? new Reference($data[$property], null)
                    : $data[$property];
            } else {
                $this->_properties[$property] = $this->instantiate($type, $data[$property]);
            }

            unset($data[$property]);
        }

        foreach ($data as $additionalProperty => $value) {
            $this->_properties[$additionalProperty] = $value;
        }
    }

    /**
     * @return mixed returns the serializable data of this object for converting it
     * to JSON or YAML.
     */
    public function getSerializableData(): object
    {
        if ($this->_recursingSerializableData) {
            // return a reference
            return (object) ['$ref' => JsonReference::createFromUri('', $this->getDocumentPosition())->getReference()];
        }

        $this->_recursingSerializableData = true;

        $data = $this->_properties;
        foreach ($data as $k => $v) {
            if ($v instanceof SpecObjectInterface) {
                $data[$k] = $v->getSerializableData();
            } elseif (is_array($v)) {
                // test if php arrays should be represented as object in YAML/JSON
                $toObject = false;
                if ($v !== []) {
                    // case 1: non-empty array should be an object if it does not contain
                    // consecutive numeric keys
                    $j = 0;
                    foreach ($v as $i => $d) {
                        if ($j++ !== $i) {
                            $toObject = true;
                        }

                        if (! ($d instanceof SpecObjectInterface)) {
                            continue;
                        }

                        $data[$k][$i] = $d->getSerializableData();
                    }
                } elseif (
                    isset($this->attributes()[$k])
                    && is_array($this->attributes()[$k])
                    && count(
                        $this->attributes()[$k],
                    ) === 2
                ) {
                    // case 2: Attribute type is an object (specified in attributes() by an array which specifies two items (key and value type)
                    $toObject = true;
                }

                if ($toObject) {
                    $data[$k] = (object) $data[$k];
                }
            }
        }

        $this->_recursingSerializableData = false;

        return (object) $data;
    }

    /**
     * Validate object data according to OpenAPI spec.
     *
     * @see getErrors()
     *
     * @return bool whether the loaded data is valid according to OpenAPI spec
     */
    public function validate(): bool
    {
        // avoid recursion to get stuck in a loop
        if ($this->_recursingValidate) {
            return true;
        }

        $this->_recursingValidate = true;
        $valid                    = true;
        foreach ($this->_properties as $v) {
            if ($v instanceof SpecObjectInterface) {
                if (! $v->validate()) {
                    $valid = false;
                }
            } elseif (is_array($v)) {
                foreach ($v as $item) {
                    if (! ($item instanceof SpecObjectInterface)) {
                        continue;
                    }

                    if ($item->validate()) {
                        continue;
                    }

                    $valid = false;
                }
            }
        }

        $this->_recursingValidate = false;

        $this->performValidation();

        if ($this->_errors !== []) {
            $valid = false;
        }

        return $valid;
    }

    /**
     * @see validate()
     *
     * @return array<string> list of validation errors according to OpenAPI spec.
     */
    public function getErrors(): array
    {
        // avoid recursion to get stuck in a loop
        if ($this->_recursingErrors) {
            return [];
        }

        $this->_recursingErrors = true;

        $errors = ($pos = $this->getDocumentPosition()) instanceof JsonPointer
            ? [
                array_map(
                    static fn ($e) => sprintf('[%s] %s', $pos->getPointer(), $e),
                    $this->_errors,
                ),
            ]
            : [$this->_errors];

        foreach ($this->_properties as $v) {
            if ($v instanceof SpecObjectInterface) {
                $errors[] = $v->getErrors();
            } elseif (is_array($v)) {
                foreach ($v as $item) {
                    if (! ($item instanceof SpecObjectInterface)) {
                        continue;
                    }

                    $errors[] = $item->getErrors();
                }
            }
        }

        $this->_recursingErrors = false;

        return array_merge(...$errors);
    }

    /** @return array<string, mixed> array of attributes default values. */
    protected function attributeDefaults(): array
    {
        return [];
    }

    /** @throws TypeErrorException */
    protected function instantiate(string $type, mixed $data): object
    {
        if ($data instanceof $type || $data instanceof Reference) {
            return $data;
        }

        if (is_array($data) && isset($data['$ref'])) {
            return new Reference($data, $type);
        }

        if (! is_array($data)) {
            throw new TypeErrorException(
                sprintf("Unable to instantiate %s Object with data '", $type) . print_r(
                    $data,
                    true,
                ) . "' at " . $this->getDocumentPosition(),
            );
        }

        try {
            return new $type($data);
        } catch (TypeError $e) {
            throw new TypeErrorException(
                sprintf("Unable to instantiate %s Object with data '", $type) . print_r(
                    $data,
                    true,
                ) . "' at " . $this->getDocumentPosition(),
                $e->getCode(),
                $e,
            );
        }
    }

    /** @param string $error error message to add. */
    protected function addError(string $error, string $class = ''): void
    {
        $shortName       = explode('\\', $class);
        $this->_errors[] = end($shortName) . $error;
    }

    /**
     * @deprecated since 1.6.0, will be removed in 2.0.0
     *
     * @param string $name property name.
     *
     * @return bool true when this object has a property with a non-null value or the property is defined in the OpenAPI spec.
     */
    protected function hasProperty(string $name): bool
    {
        return isset($this->_properties[$name]) || isset($this->attributes()[$name]);
    }

    /**
     * @param string $name property name.
     *
     * @return bool true, when a property has a non-null value (does not check for default values)
     */
    protected function hasPropertyValue(string $name): bool
    {
        return isset($this->_properties[$name]);
    }

    /**
     * @param list<string> $names
     * @param list<string> $atLeastOne
     */
    protected function requireProperties(array $names, array $atLeastOne = []): void
    {
        foreach ($names as $name) {
            if (isset($this->_properties[$name])) {
                continue;
            }

            $this->addError(' is missing required property: ' . $name, static::class);
        }

        if (count($atLeastOne) <= 0) {
            return;
        }

        foreach ($atLeastOne as $name) {
            if (array_key_exists($name, $this->_properties)) {
                return;
            }
        }

        $this->addError(
            ' is missing at least one of the following required properties: ' . implode(', ', $atLeastOne),
            static::class,
        );
    }

    protected function validateEmail(string $property): void
    {
        if (empty($this->$property) || str_contains((string) $this->$property, '@')) {
            return;
        }

        $this->addError(
            '::$' . $property . ' does not seem to be a valid email address: ' . $this->$property,
            static::class,
        );
    }

    protected function validateUrl(string $property): void
    {
        if (empty($this->$property) || str_contains((string) $this->$property, '//')) {
            return;
        }

        $this->addError('::$' . $property . ' does not seem to be a valid URL: ' . $this->$property, static::class);
    }

    public function __get(string $name): mixed
    {
        if (isset($this->_properties[$name])) {
            return $this->_properties[$name];
        }

        $defaults = $this->attributeDefaults();
        if (array_key_exists($name, $defaults)) {
            return $defaults[$name];
        }

        if (isset($this->attributes()[$name])) {
            if (is_array($this->attributes()[$name])) {
                return [];
            }

            if ($this->attributes()[$name] === Type::BOOLEAN) {
                return false;
            }

            return null;
        }

        throw new UnknownPropertyException(
            'Getting unknown property: ' . static::class . '::' . $name,
        );
    }

    public function __set(string $name, mixed $value): void
    {
        $this->_properties[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        if (
            isset($this->_properties[$name])
            || isset($this->attributeDefaults()[$name])
            || isset($this->attributes()[$name])
        ) {
            return $this->__get($name) !== null;
        }

        return false;
    }

    public function __unset(string $name): void
    {
        unset($this->_properties[$name]);
    }

    /**
     * Resolves all Reference Objects in this object and replaces them with their resolution.
     *
     * @throws UnresolvableReferenceException in case resolving a reference fails.
     */
    public function resolveReferences(ReferenceContext|null $context = null): void
    {
        // avoid recursion to get stuck in a loop
        if ($this->_recursingReferences) {
            return;
        }

        $this->_recursingReferences = true;

        foreach ($this->_properties as $property => $value) {
            if ($value instanceof Reference) {
                $referencedObject             = $value->resolve($context);
                $this->_properties[$property] = $referencedObject;
                if (! $referencedObject instanceof Reference && $referencedObject instanceof SpecObjectInterface) {
                    $referencedObject->resolveReferences();
                }
            } elseif ($value instanceof SpecObjectInterface) {
                $value->resolveReferences($context);
            } elseif (is_array($value)) {
                foreach ($value as $k => $item) {
                    if ($item instanceof Reference) {
                        $referencedObject                 = $item->resolve($context);
                        $this->_properties[$property][$k] = $referencedObject;
                        if (
                            ! $referencedObject instanceof Reference
                            && $referencedObject instanceof SpecObjectInterface
                        ) {
                            $referencedObject->resolveReferences();
                        }
                    } elseif ($item instanceof SpecObjectInterface) {
                        $item->resolveReferences($context);
                    }
                }
            }
        }

        $this->_recursingReferences = false;
    }

    /**
     * Set context for all Reference Objects in this object.
     */
    public function setReferenceContext(ReferenceContext $context): void
    {
        // avoid recursion to get stuck in a loop
        if ($this->_recursingReferenceContext) {
            return;
        }

        $this->_recursingReferenceContext = true;

        foreach ($this->_properties as $value) {
            if ($value instanceof Reference) {
                $value->setContext($context);
            } elseif ($value instanceof SpecObjectInterface) {
                $value->setReferenceContext($context);
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Reference) {
                        $item->setContext($context);
                    } elseif ($item instanceof SpecObjectInterface) {
                        $item->setReferenceContext($context);
                    }
                }
            }
        }

        $this->_recursingReferenceContext = false;
    }

    /**
     * Provide context information to the object.
     *
     * Context information contains a reference to the base object where it is contained in
     * as well as a JSON pointer to its position.
     */
    public function setDocumentContext(SpecObjectInterface $baseDocument, JsonPointer $jsonPointer): void
    {
        $this->_baseDocument = $baseDocument;
        $this->_jsonPointer  = $jsonPointer;

        // avoid recursion to get stuck in a loop
        if ($this->_recursingDocumentContext) {
            return;
        }

        $this->_recursingDocumentContext = true;

        foreach ($this->_properties as $property => $value) {
            if ($value instanceof DocumentContextInterface) {
                $value->setDocumentContext($baseDocument, $jsonPointer->append($property));
            } elseif (is_array($value)) {
                foreach ($value as $k => $item) {
                    if (! ($item instanceof DocumentContextInterface)) {
                        continue;
                    }

                    $item->setDocumentContext($baseDocument, $jsonPointer->append($property)->append((string) $k));
                }
            }
        }

        $this->_recursingDocumentContext = false;
    }

    /**
     * @return SpecObjectInterface|null returns the base document where this object is located in.
     * Returns `null` if no context information was provided by [[setDocumentContext]].
     */
    public function getBaseDocument(): SpecObjectInterface|null
    {
        return $this->_baseDocument;
    }

    /**
     * @return JsonPointer|null returns a JSON pointer describing the position of this object in the base document.
     * Returns `null` if no context information was provided by [[setDocumentContext]].
     */
    public function getDocumentPosition(): JsonPointer|null
    {
        return $this->_jsonPointer;
    }

    /**
     * Returns extension properties with `x-` prefix.
     *
     * @see https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#specificationExtensions
     *
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        $extensions = [];
        foreach ($this->_properties as $propertyKey => $extension) {
            if (! str_starts_with((string) $propertyKey, 'x-')) {
                continue;
            }

            $extensions[$propertyKey] = $extension;
        }

        return $extensions;
    }
}
