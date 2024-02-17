<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\DocumentContextInterface;
use openapiphp\openapi\exceptions\TypeErrorException;
use openapiphp\openapi\exceptions\UnresolvableReferenceException;
use openapiphp\openapi\json\InvalidJsonPointerSyntaxException;
use openapiphp\openapi\json\JsonPointer;
use openapiphp\openapi\json\JsonReference;
use openapiphp\openapi\json\NonexistentJsonPointerReferenceException;
use openapiphp\openapi\ReferenceContext;
use openapiphp\openapi\SpecObjectInterface;
use Throwable;

use function array_map;
use function count;
use function dirname;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function is_subclass_of;
use function print_r;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Reference Object
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#referenceObject
 * @link https://tools.ietf.org/html/draft-pbryan-zyp-json-ref-03
 * @link https://tools.ietf.org/html/rfc6901
 */
class Reference implements SpecObjectInterface, DocumentContextInterface
{
    private string|null $_to;
    private readonly string $_ref;
    private JsonReference|null $_jsonReference = null;
    private ReferenceContext $_context;
    private SpecObjectInterface|null $_baseDocument = null;
    private JsonPointer|null $_jsonPointer          = null;
    /** @var list<string> */
    private array $_errors = [];

    /** @inheritDoc */
    public function __construct(array $data, string|null $to = null)
    {
        if (! isset($data['$ref'])) {
            throw new TypeErrorException(
                "Unable to instantiate Reference Object with data '" . print_r($data, true) . "'.",
            );
        }

        if ($to !== null && ! is_subclass_of($to, SpecObjectInterface::class, true)) {
            throw new TypeErrorException(
                'Unable to instantiate Reference Object, Referenced Class type must implement SpecObjectInterface.',
            );
        }

        if (! is_string($data['$ref'])) {
            throw new TypeErrorException(
                'Unable to instantiate Reference Object, value of $ref must be a string.',
            );
        }

        $this->_to  = $to;
        $this->_ref = $data['$ref'];
        try {
            $this->_jsonReference = JsonReference::createFromReference($this->_ref);
        } catch (InvalidJsonPointerSyntaxException $e) {
            $this->_errors[] = 'Reference: value of $ref is not a valid JSON pointer: ' . $e->getMessage();
        }

        if (count($data) === 1) {
            return;
        }

        $this->_errors[] = 'Reference: additional properties are given. Only $ref should be set in a Reference Object.';
    }

    /**
     * @return mixed returns the serializable data of this object for converting it
     * to JSON or YAML.
     */
    public function getSerializableData(): object
    {
        return (object) ['$ref' => $this->_ref];
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
        return $this->_errors === [];
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
            return array_map(static fn ($e) => sprintf('[%s] %s', $pos, $e), $this->_errors);
        }

        return $this->_errors;
    }

    /** @return string the reference string. */
    public function getReference(): string
    {
        return $this->_ref;
    }

    /** @return JsonReference the JSON Reference. */
    public function getJsonReference(): JsonReference|null
    {
        return $this->_jsonReference;
    }

    public function setContext(ReferenceContext $context): void
    {
        $this->_context = $context;
    }

    /** @return ReferenceContext */
    public function getContext(): ReferenceContext|null
    {
        return $this->_context;
    }

    /**
     * Resolve this reference.
     *
     * @param ReferenceContext $context the reference context to use for resolution.
     * If not specified, `getContext()` will be called to determine the context, if
     * that does not return a context, the UnresolvableReferenceException will be thrown.
     *
     * @return SpecObjectInterface|array<mixed>|null the resolved spec type.
     * You might want to call resolveReferences() on the resolved object to recursively resolve recursive references.
     * This is not done automatically to avoid recursion to run into the same function again.
     * If you call resolveReferences() make sure to replace the Reference with the resolved object first.
     *
     * @throws UnresolvableReferenceException in case of errors.
     */
    public function resolve(ReferenceContext|null $context = null): SpecObjectInterface|array|string|null
    {
        if (! $context instanceof ReferenceContext) {
            $context = $this->getContext();
            if (! $context instanceof ReferenceContext) {
                throw new UnresolvableReferenceException('No context given for resolving reference.');
            }
        }

        $jsonReference = $this->_jsonReference;
        if (! $jsonReference instanceof JsonReference) {
            if ($context->throwException) {
                throw new UnresolvableReferenceException(implode("\n", $this->getErrors()));
            }

            return $this;
        }

        try {
            if ($jsonReference->getDocumentUri() === '') {
                if ($context->mode === ReferenceContext::RESOLVE_MODE_INLINE) {
                    return $this;
                }

                // resolve in current document
                $baseSpec = $context->getBaseSpec();
                if ($baseSpec instanceof SpecObjectInterface) {
                    $referencedObject = $jsonReference->getJsonPointer()->evaluate($baseSpec);
                    // transitive reference
                    if ($referencedObject instanceof Reference) {
                        $referencedObject = $this->resolveTransitiveReference($referencedObject, $context);
                    }

                    if ($referencedObject instanceof SpecObjectInterface) {
                        $referencedObject->setReferenceContext($context);
                    }

                    return $referencedObject;
                } else {
                    // if current document was loaded via reference, it may be null,
                    // so we load current document by URI instead.
                    $jsonReference = JsonReference::createFromUri($context->getUri(), $jsonReference->getJsonPointer());
                }
            }

            // resolve in external document
            $file = $context->resolveRelativeUri($jsonReference->getDocumentUri());
            try {
                $referencedDocument = $context->fetchReferencedFile($file);
            } catch (Throwable $e) {
                $exception          = new UnresolvableReferenceException(
                    sprintf('Failed to resolve Reference \'%s\' to %s Object: ', $this->_ref, $this->_to) . $e->getMessage(),
                    $e->getCode(),
                    $e,
                );
                $exception->context = $this->getDocumentPosition();

                throw $exception;
            }

            $referencedDocument = $this->adjustRelativeReferences($referencedDocument, $file, null, $context);
            $referencedObject   = $context->resolveReferenceData($file, $jsonReference->getJsonPointer(), $referencedDocument, $this->_to);

            if ($referencedObject instanceof DocumentContextInterface && (! $referencedObject->getDocumentPosition() instanceof JsonPointer && $this->getDocumentPosition() instanceof JsonPointer)) {
                $referencedObject->setDocumentContext($context->getBaseSpec(), $this->getDocumentPosition());
            }

            // transitive reference
            if ($referencedObject instanceof Reference) {
                if ($context->mode !== ReferenceContext::RESOLVE_MODE_INLINE || ! str_starts_with($referencedObject->getReference(), '#')) {
                    return $this->resolveTransitiveReference($referencedObject, $context);
                }

                $referencedObject->setContext($context);
            } elseif ($referencedObject instanceof SpecObjectInterface) {
                $referencedObject->setReferenceContext($context);
            }

            return $referencedObject;
        } catch (NonexistentJsonPointerReferenceException $e) {
            $message = sprintf('Failed to resolve Reference \'%s\' to %s Object: ', $this->_ref, $this->_to) . $e->getMessage();
            if ($context->throwException) {
                $exception          = new UnresolvableReferenceException($message, 0, $e);
                $exception->context = $this->getDocumentPosition();

                throw $exception;
            }

            $this->_errors[]      = $message;
            $this->_jsonReference = null;

            return $this;
        } catch (UnresolvableReferenceException $e) {
            $e->context = $this->getDocumentPosition();
            if ($context->throwException) {
                throw $e;
            }

            $this->_errors[]      = $e->getMessage();
            $this->_jsonReference = null;

            return $this;
        }
    }

    private function resolveTransitiveReference(Reference $referencedObject, ReferenceContext $context): SpecObjectInterface|array|null
    {
        if ($referencedObject->_to === null) {
            $referencedObject->_to = $this->_to;
        }

        $referencedObject->setContext($context);

        if ($referencedObject === $this) { // catch recursion
            throw new UnresolvableReferenceException('Cyclic reference detected on a Reference Object.');
        }

        $transitiveRefResult = $referencedObject->resolve();

        if ($transitiveRefResult === $this) { // catch recursion
            throw new UnresolvableReferenceException('Cyclic reference detected on a Reference Object.');
        }

        return $transitiveRefResult;
    }

    private bool $_recursingInsideFile = false;

    /**
     * Adjust relative references inside of the file to match the context of the base file
     *
     * @param array<string, mixed> $referencedDocument
     *
     * @return array<string, mixed>
     */
    private function adjustRelativeReferences(array $referencedDocument, string $basePath, mixed $baseDocument = null, ReferenceContext|null $oContext = null): array
    {
        $context = new ReferenceContext(null, $basePath);
        if ($baseDocument === null) {
            $baseDocument = $referencedDocument;
        }

        foreach ($referencedDocument as $key => $value) {
            // adjust reference URLs
            if ($key === '$ref' && is_string($value)) {
                if (isset($value[0]) && $value[0] === '#') {
                    // direcly inline references in the same document,
                    // these are not going to be valid in the new context anymore
                    $inlineDocument = (new JsonPointer(substr($value, 1)))->evaluate($baseDocument);
                    if ($this->_recursingInsideFile) {
                        // keep reference when it is a recursive reference
                        return ['$ref' => $basePath . $value];
                    }

                    $this->_recursingInsideFile = true;
                    $return                     = $this->adjustRelativeReferences($inlineDocument, $basePath, $baseDocument, $oContext);
                    $this->_recursingInsideFile = false;

                    return $return;
                }

                if (! $oContext instanceof ReferenceContext) {
                    continue;
                }

                $referencedDocument[$key] = $context->resolveRelativeUri($value);
                $parts                    = explode('#', $referencedDocument[$key], 2);
                if ($parts[0] === $oContext->getUri()) {
                    $referencedDocument[$key] = '#' . ($parts[1] ?? '');
                } else {
                    $referencedDocument[$key] = $this->makeRelativePath($oContext->getUri(), $referencedDocument[$key]);
                }

                continue;
            }

            // adjust URLs for 'externalValue' references in Example Objects
            // https://spec.openapis.org/oas/v3.0.3#example-object
            if ($key === 'externalValue' && is_string($value) && $oContext instanceof ReferenceContext) {
                $referencedDocument[$key] = $this->makeRelativePath($oContext->getUri(), $context->resolveRelativeUri($value));
                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $referencedDocument[$key] = $this->adjustRelativeReferences($value, $basePath, $baseDocument, $oContext);
        }

        return $referencedDocument;
    }

    /**
     * If $path can be expressed relative to $base, make it a relative path, otherwise $path is returned.
     */
    private function makeRelativePath(string $base, string $path): string
    {
        if (str_starts_with($path, dirname($base))) {
            return './' . substr($path, strlen(dirname($base) . '/'));
        }

        return $path;
    }

    /**
     * Resolves all Reference Objects in this object and replaces them with their resolution.
     *
     * @throws UnresolvableReferenceException
     */
    public function resolveReferences(ReferenceContext|null $context = null): never
    {
        throw new UnresolvableReferenceException('Cyclic reference detected, resolveReferences() called on a Reference Object.');
    }

    /**
     * Set context for all Reference Objects in this object.
     *
     * @throws UnresolvableReferenceException
     */
    public function setReferenceContext(ReferenceContext $context): never
    {
        throw new UnresolvableReferenceException('Cyclic reference detected, setReferenceContext() called on a Reference Object.');
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
