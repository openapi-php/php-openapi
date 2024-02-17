<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\exceptions\UnresolvableReferenceException;
use openapiphp\openapi\json\JsonPointer;
use openapiphp\openapi\ReferenceContext;
use openapiphp\openapi\SpecBaseObject;
use openapiphp\openapi\SpecObjectInterface;

use function array_keys;
use function is_array;
use function sprintf;

/**
 * Describes the operations available on a single path.
 *
 * A Path Item MAY be empty, due to ACL constraints. The path itself is still exposed to the documentation
 * viewer but they will not know which operations and parameters are available.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#pathItemObject
 *
 * @property string $summary
 * @property string $description
 * @property Operation|null $get
 * @property Operation|null $put
 * @property Operation|null $post
 * @property Operation|null $delete
 * @property Operation|null $options
 * @property Operation|null $head
 * @property Operation|null $patch
 * @property Operation|null $trace
 * @property array<Server> $servers
 * @property array<Parameter>|array<Reference> $parameters
 */
final class PathItem extends SpecBaseObject
{
    private Reference|null $_ref = null;

    /** @inheritDoc */
    public function __construct(array $data)
    {
        if (isset($data['$ref'])) {
            // Allows for an external definition of this path item.
            // $ref in a Path Item Object is not a Reference.
            // https://github.com/OAI/OpenAPI-Specification/issues/1038
            $this->_ref = new Reference(['$ref' => $data['$ref']], self::class);
            unset($data['$ref']);
        }

        parent::__construct($data);
    }

    /**
     * @return object returns the serializable data of this object for converting it
     * to JSON or YAML.
     */
    public function getSerializableData(): object
    {
        $data = parent::getSerializableData();
        if ($this->_ref instanceof Reference) {
            $data->{'$ref'} = $this->_ref->getReference();
        }

        if (isset($data->servers) && empty($data->servers)) {
            unset($data->servers);
        }

        if (isset($data->parameters) && empty($data->parameters)) {
            unset($data->parameters);
        }

        return $data;
    }

    /**
     * Return all operations of this Path.
     *
     * @return array<Operation>
     */
    public function getOperations(): array
    {
        $operations = [];
        foreach ($this->attributes() as $attribute => $type) {
            if ($type !== Operation::class || ! isset($this->$attribute)) {
                continue;
            }

            $operations[$attribute] = $this->$attribute;
        }

        return $operations;
    }

    /**
     * Allows for an external definition of this path item. The referenced structure MUST be in the format of a
     * PathItem Object. The properties of the referenced structure are merged with the local Path Item Object.
     * If the same property exists in both, the referenced structure and the local one, this is a conflict.
     * In this case the behavior is *undefined*.
     */
    public function getReference(): Reference|null
    {
        return $this->_ref;
    }

    /**
     * Set context for all Reference Objects in this object.
     */
    public function setReferenceContext(ReferenceContext $context): void
    {
        if ($this->_ref instanceof Reference) {
            $this->_ref->setContext($context);
        }

        parent::setReferenceContext($context);
    }

    /**
     * Resolves all Reference Objects in this object and replaces them with their resolution.
     *
     * @throws UnresolvableReferenceException in case resolving a reference fails.
     */
    public function resolveReferences(ReferenceContext|null $context = null): void
    {
        if ($this->_ref instanceof Reference) {
            $pathItem   = $this->_ref->resolve($context);
            $this->_ref = null;
            // The properties of the referenced structure are merged with the local Path Item Object.
            foreach (array_keys(self::attributes()) as $attribute) {
                if (! isset($pathItem->$attribute)) {
                    continue;
                }

                // If the same property exists in both, the referenced structure and the local one, this is a conflict.
                if (isset($this->$attribute) && ! empty($this->$attribute)) {
                    $this->addError(sprintf('Conflicting properties, property \'%s\' exists in local PathItem and also in the referenced one.', $attribute));
                }

                $this->$attribute = $pathItem->$attribute;

                // resolve references in all properties assinged from the reference
                // use the referenced object context in this case
                if ($this->$attribute instanceof Reference) {
                    $referencedObject = $this->$attribute->resolve();
                    $this->$attribute = $referencedObject;
                    if (! $referencedObject instanceof Reference && $referencedObject !== null) {
                        $referencedObject->resolveReferences();
                    }
                } elseif ($this->$attribute instanceof SpecObjectInterface) {
                    $this->$attribute->resolveReferences();
                } elseif (is_array($this->$attribute)) {
                    foreach ($this->$attribute as $k => $item) {
                        if ($item instanceof Reference) {
                            $referencedObject = $item->resolve();
                            $this->$attribute = [$k => $referencedObject] + $this->$attribute;
                            if (! $referencedObject instanceof Reference && $referencedObject !== null) {
                                $referencedObject->resolveReferences();
                            }
                        } elseif ($item instanceof SpecObjectInterface) {
                            $item->resolveReferences();
                        }
                    }
                }
            }

            if ($pathItem instanceof SpecBaseObject) {
                foreach ($pathItem->getExtensions() as $extensionKey => $extension) {
                    $this->{$extensionKey} = $extension;
                }
            }
        }

        parent::resolveReferences($context);
    }

    /**
     * Provide context information to the object.
     *
     * Context information contains a reference to the base object where it is contained in
     * as well as a JSON pointer to its position.
     */
    public function setDocumentContext(SpecObjectInterface $baseDocument, JsonPointer $jsonPointer): void
    {
        parent::setDocumentContext($baseDocument, $jsonPointer);

        if (! ($this->_ref instanceof Reference)) {
            return;
        }

        $this->_ref->setDocumentContext($baseDocument, $jsonPointer->append('$ref'));
    }

    /** @inheritDoc */
    protected function attributes(): array
    {
        return [
            'delete' => Operation::class,
            'description' => Type::STRING,
            'get' => Operation::class,
            'head' => Operation::class,
            'options' => Operation::class,
            'parameters' => [Parameter::class],
            'patch' => Operation::class,
            'post' => Operation::class,
            'put' => Operation::class,
            'servers' => [Server::class],
            'summary' => Type::STRING,
            'trace' => Operation::class,
        ];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    protected function performValidation(): void
    {
        // no required arguments
    }
}
