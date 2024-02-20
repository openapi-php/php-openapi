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
use openapiphp\openapi\OpenApiVersion;
use openapiphp\openapi\ReferenceContext;
use openapiphp\openapi\ReferenceTarget;
use openapiphp\openapi\SpecObjectInterface;
use Traversable;

use function array_map;
use function array_merge;
use function assert;
use function count;
use function gettype;
use function is_array;
use function is_numeric;
use function is_object;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * A container for the expected responses of an operation.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#responsesObject
 *
 * @implements ArrayAccess<string, Response|Reference|null>
 * @implements IteratorAggregate<string, Response|Reference|null>
 */
class Responses implements SpecObjectInterface, DocumentContextInterface, ArrayAccess, Countable, IteratorAggregate
{
    /** @var array<string, Response|Reference|null> */
    private array $_responses = [];

    /** @var list<string> */
    private array $_errors = [];

    private SpecObjectInterface|null $_baseDocument = null;
    private JsonPointer|null $_jsonPointer          = null;

    /**
     * Create an object from spec data.
     *
     * @param array<string, Response|Reference|object|array<string, mixed>|null> $data spec data read from YAML or JSON
     *
     * @throws TypeErrorException in case invalid data is supplied.
     */
    public function __construct(array $data, private readonly OpenApiVersion|null $openApiVersion = null)
    {
        foreach ($data as $statusCode => $response) {
            // From Spec: This field MUST be enclosed in quotation marks (for example, "200") for compatibility between JSON and YAML.
            $statusCode = (string) $statusCode;
            if (preg_match('~^(?:default|[1-5](?:\d\d|XX))$~', $statusCode)) {
                if ($response instanceof Response || $response instanceof Reference) {
                    $this->_responses[$statusCode] = $response;
                } elseif (is_array($response) && isset($response['$ref'])) {
                    $this->_responses[$statusCode] = new Reference($response, $this->openApiVersion, new ReferenceTarget($this));
                } elseif (is_array($response)) {
                    $this->_responses[$statusCode] = new Response($response, $this->openApiVersion);
                } else {
                    $givenType = gettype($response);
                    if (is_object($response)) {
                        $givenType = $response::class;
                    }

                    throw new TypeErrorException(sprintf('Response MUST be either an array, a Response or a Reference object, "%s" given', $givenType));
                }
            } else {
                $this->_errors[] = sprintf('Responses: %s is not a valid HTTP status code.', $statusCode);
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
        foreach ($this->_responses as $statusCode => $response) {
            $data[$statusCode] = $response?->getSerializableData();
        }

        return (object) $data;
    }

    /** @param string $statusCode HTTP status code */
    public function hasResponse(string $statusCode): bool
    {
        return isset($this->_responses[$statusCode]);
    }

    /** @param string $statusCode HTTP status code */
    public function getResponse(string $statusCode): Response|Reference|null
    {
        return $this->_responses[$statusCode] ?? null;
    }

    /** @param string $statusCode HTTP status code */
    public function addResponse(string $statusCode, Response|Reference $response): void
    {
        $this->_responses[$statusCode] = $response;
    }

    /** @param string $statusCode HTTP status code */
    public function removeResponse(string $statusCode): void
    {
        unset($this->_responses[$statusCode]);
    }

    /** @return (Response|Reference|null)[] */
    public function getResponses(): array
    {
        return $this->_responses;
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
        $valid = true;
        foreach ($this->_responses as $response) {
            if ($response === null) {
                continue;
            }

            if ($response->validate()) {
                continue;
            }

            $valid = false;
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

        foreach ($this->_responses as $response) {
            if ($response === null) {
                continue;
            }

            $errors[] = $response->getErrors();
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
        if (! is_string($offset) && ! is_numeric($offset)) {
            return false;
        }

        return $this->hasResponse((string) $offset);
    }

    /**
     * Offset to retrieve
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset The offset to retrieve.
     *
     * @return mixed Can return all value types.
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (! is_string($offset) && ! is_numeric($offset)) {
            return null;
        }

        return $this->getResponse((string) $offset);
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
        if (! is_string($offset) && ! is_numeric($offset)) {
            return;
        }

        if (! ($value instanceof Response) && ! ($value instanceof Reference)) {
            return;
        }

        $this->addResponse((string) $offset, $value);
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
        if (! is_string($offset) && ! is_numeric($offset)) {
            return;
        }

        $this->removeResponse((string) $offset);
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
        return count($this->_responses);
    }

    /**
     * Retrieve an external iterator
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     *
     * @return Traversable<string, Response|Reference|null> An instance of an object implementing <b>Iterator</b> or <b>Traversable</b>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->_responses);
    }

    /**
     * Resolves all Reference Objects in this object and replaces them with their resolution.
     *
     * @throws UnresolvableReferenceException
     */
    public function resolveReferences(ReferenceContext|null $context = null): void
    {
        foreach ($this->_responses as $key => $response) {
            if ($response instanceof Reference) {
                $referencedObject = $response->resolve($context);
                assert($referencedObject instanceof Response || $referencedObject instanceof Reference || $referencedObject === null);
                $this->_responses[$key] = $referencedObject;
                if (! $referencedObject instanceof Reference && $referencedObject instanceof SpecObjectInterface) {
                    $referencedObject->resolveReferences();
                }
            } elseif ($response instanceof SpecObjectInterface) {
                $response->resolveReferences($context);
            }
        }
    }

    /**
     * Set context for all Reference Objects in this object.
     */
    public function setReferenceContext(ReferenceContext $context): void
    {
        foreach ($this->_responses as $response) {
            if ($response instanceof Reference) {
                $response->setContext($context);
            } elseif ($response instanceof Response) {
                $response->setReferenceContext($context);
            }
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

        foreach ($this->_responses as $key => $response) {
            if (! ($response instanceof DocumentContextInterface)) {
                continue;
            }

            $response->setDocumentContext($baseDocument, $jsonPointer->append((string) $key));
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

    public function getApiVersion(): OpenApiVersion
    {
        return $this->openApiVersion ?? OpenApiVersion::VERSION_UNSUPPORTED;
    }

    /** @inheritDoc */
    public function attributes(): array
    {
        return [
            [Type::STRING, Response::class],
        ];
    }
}
