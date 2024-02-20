<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\DocumentContextInterface;
use openapiphp\openapi\exceptions\UnresolvableReferenceException;
use openapiphp\openapi\json\JsonPointer;
use openapiphp\openapi\OpenApiVersion;
use openapiphp\openapi\ReferenceContext;
use openapiphp\openapi\SpecObjectInterface;

use function array_map;
use function array_merge;
use function count;
use function key;
use function sprintf;

/**
 * A map of possible out-of band callbacks related to the parent operation.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#callbackObject
 */
class Callback implements SpecObjectInterface, DocumentContextInterface
{
    private string|null $_url = null;
    private PathItem|null $_pathItem;
    /** @var list<string> */
    private array $_errors                          = [];
    private SpecObjectInterface|null $_baseDocument = null;
    private JsonPointer|null $_jsonPointer          = null;

    /** @inheritDoc */
    public function __construct(array $data, private readonly OpenApiVersion|null $openApiVersion = null)
    {
        if (count($data) !== 1) {
            $this->_errors[] = 'Callback object must have exactly one URL.';

            return;
        }

        $this->_url      = key($data);
        $this->_pathItem = new PathItem($data[$this->_url], $this->openApiVersion);
    }

    /**
     * @return mixed returns the serializable data of this object for converting it
     * to JSON or YAML.
     */
    public function getSerializableData(): object
    {
        return (object) [$this->_url => $this->_pathItem?->getSerializableData()];
    }

    public function getUrl(): string|null
    {
        return $this->_url;
    }

    public function setUrl(string $url): void
    {
        $this->_url = $url;
    }

    /** @return PathItem */
    public function getRequest(): PathItem|null
    {
        return $this->_pathItem;
    }

    /** @param PathItem $request */
    public function setRequest(PathItem|null $request): void
    {
        $this->_pathItem = $request;
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
        $pathItemValid = ! $this->_pathItem instanceof PathItem || $this->_pathItem->validate();

        return $pathItemValid && $this->_errors === [];
    }

    /**
     * @see validate()
     *
     * @return string[] list of validation errors according to OpenAPI spec.
     */
    public function getErrors(): array
    {
        $pos    = $this->getDocumentPosition();
        $errors = $pos instanceof JsonPointer ? array_map(static fn ($e) => sprintf('[%s] %s', $pos, $e), $this->_errors) : $this->_errors;

        $pathItemErrors = $this->_pathItem instanceof PathItem ? $this->_pathItem->getErrors() : [];

        return array_merge($errors, $pathItemErrors);
    }

    /**
     * Resolves all Reference Objects in this object and replaces them with their resolution.
     *
     * @throws UnresolvableReferenceException
     */
    public function resolveReferences(ReferenceContext|null $context = null): void
    {
        $this->_pathItem->resolveReferences($context);
    }

    /**
     * Set context for all Reference Objects in this object.
     */
    public function setReferenceContext(ReferenceContext $context): void
    {
        $this->_pathItem->setReferenceContext($context);
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

        if ($this->_url === null) {
            return;
        }

        $this->_pathItem->setDocumentContext($baseDocument, $jsonPointer->append($this->_url));
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
            [Type::STRING, PathItem::class],
        ];
    }
}
