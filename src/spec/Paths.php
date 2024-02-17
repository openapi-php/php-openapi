<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use openapiphp\openapi\DocumentContextInterface;
use openapiphp\openapi\exceptions\TypeErrorException;
use openapiphp\openapi\exceptions\UnresolvableReferenceException;
use openapiphp\openapi\json\JsonPointer;
use openapiphp\openapi\ReferenceContext;
use openapiphp\openapi\SpecObjectInterface;
use Traversable;

use function array_map;
use function array_merge;
use function count;
use function gettype;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;
use function str_starts_with;

/**
 * Holds the relative paths to the individual endpoints and their operations.
 *
 * The path is appended to the URL from the Server Object in order to construct the full URL.
 * The Paths MAY be empty, due to ACL constraints.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#pathsObject
 *
 * @implements ArrayAccess<string, PathItem|null>
 * @implements IteratorAggregate<string, PathItem|null>
 */
class Paths implements SpecObjectInterface, DocumentContextInterface, ArrayAccess, Countable, IteratorAggregate
{
    /** @var array<string, PathItem|null> */
    private array $_paths = [];
    /** @var list<string> */
    private array $_errors                          = [];
    private SpecObjectInterface|null $_baseDocument = null;
    private JsonPointer|null $_jsonPointer          = null;

    /**
     * Create an object from spec data.
     *
     * @param array<string, PathItem|array<string, mixed>|null> $data spec data read from YAML or JSON
     *
     * @throws TypeErrorException in case invalid data is supplied.
     */
    public function __construct(array $data)
    {
        foreach ($data as $path => $object) {
            if ($object === null) {
                $this->_paths[$path] = null;
            } elseif (is_array($object)) {
                $this->_paths[$path] = new PathItem($object);
            } elseif ($object instanceof PathItem) {
                $this->_paths[$path] = $object;
            } else {
                $givenType = gettype($object);
                if (is_object($object)) {
                    $givenType = $object::class;
                }

                throw new TypeErrorException(sprintf('Path MUST be either array or PathItem object, "%s" given', $givenType));
            }
        }
    }

    /**
     * @return object returns the serializable data of this object for converting it
     * to JSON or YAML.
     */
    public function getSerializableData(): object
    {
        $data = [];
        foreach ($this->_paths as $path => $pathItem) {
            $data[$path] = $pathItem?->getSerializableData();
        }

        return (object) $data;
    }

    /** @param string $name path name */
    public function hasPath(string $name): bool
    {
        return isset($this->_paths[$name]);
    }

    /**
     * @param string $name path name
     *
     * @return PathItem
     */
    public function getPath(string $name): PathItem|null
    {
        return $this->_paths[$name] ?? null;
    }

    /**
     * @param string   $name     path name
     * @param PathItem $pathItem the path item to add
     */
    public function addPath(string $name, PathItem $pathItem): void
    {
        $this->_paths[$name] = $pathItem;
    }

    /** @param string $name path name */
    public function removePath(string $name): void
    {
        unset($this->_paths[$name]);
    }

    /** @return array<string, PathItem|null> */
    public function getPaths(): array
    {
        return $this->_paths;
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
        $valid         = true;
        $this->_errors = [];
        foreach ($this->_paths as $key => $path) {
            if ($path === null) {
                continue;
            }

            if (! $path->validate()) {
                $valid = false;
            }

            if (str_starts_with($key, '/')) {
                continue;
            }

            $this->_errors[] = 'Path must begin with /: ' . $key;
        }

        return $valid && $this->_errors === [];
    }

    /**
     * @see validate()
     *
     * @return string[] list of validation errors according to OpenAPI spec.
     */
    public function getErrors(): array
    {
        $pos = $this->getDocumentPosition();
        if ($pos instanceof JsonPointer) {
            $errors = [array_map(static fn ($e) => sprintf('[%s] %s', $pos, $e), $this->_errors)];
        } else {
            $errors = [$this->_errors];
        }

        foreach ($this->_paths as $path) {
            if ($path === null) {
                continue;
            }

            $errors[] = $path->getErrors();
        }

        return array_merge(...$errors);
    }

    /**
     * Whether a offset exists
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset An offset to check for.
     *
     * @return bool true on success or false on failure.
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists(mixed $offset): bool
    {
        if (! is_string($offset)) {
            return false;
        }

        return $this->hasPath($offset);
    }

    /**
     * Offset to retrieve
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset The offset to retrieve.
     *
     * @return PathItem Can return all value types.
     */
    public function offsetGet(mixed $offset): PathItem|null
    {
        if (! is_string($offset)) {
            return null;
        }

        return $this->getPath($offset);
    }

    /**
     * Offset to set
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value  The value to set.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (! is_string($offset) || ! $value instanceof PathItem) {
            return;
        }

        $this->addPath($offset, $value);
    }

    /**
     * Offset to unset
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset The offset to unset.
     */
    public function offsetUnset(mixed $offset): void
    {
        if (! is_string($offset)) {
            return;
        }

        $this->removePath($offset);
    }

    /**
     * Count elements of an object
     *
     * @link http://php.net/manual/en/countable.count.php
     *
     * @return int The custom count as an integer.
     * The return value is cast to an integer.
     */
    public function count(): int
    {
        return count($this->_paths);
    }

    /**
     * Retrieve an external iterator
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     *
     * @return Traversable<PathItem|null> An instance of an object implementing <b>Iterator</b> or <b>Traversable</b>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->_paths);
    }

    /**
     * Resolves all Reference Objects in this object and replaces them with their resolution.
     *
     * @throws UnresolvableReferenceException
     */
    public function resolveReferences(ReferenceContext|null $context = null): void
    {
        foreach ($this->_paths as $path) {
            if ($path === null) {
                continue;
            }

            $path->resolveReferences($context);
        }
    }

    /**
     * Set context for all Reference Objects in this object.
     */
    public function setReferenceContext(ReferenceContext $context): void
    {
        foreach ($this->_paths as $path) {
            if ($path === null) {
                continue;
            }

            $path->setReferenceContext($context);
        }
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

        foreach ($this->_paths as $key => $path) {
            if (! ($path instanceof DocumentContextInterface)) {
                continue;
            }

            $path->setDocumentContext($baseDocument, $jsonPointer->append($key));
        }
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
}
