<?php

declare(strict_types=1);

namespace openapiphp\openapi;

use openapiphp\openapi\exceptions\TypeErrorException;

/**
 * This interface is implemented by all classes that represent objects from the OpenAPI Spec.
 */
interface SpecObjectInterface
{
    /**
     * Create an object from spec data.
     *
     * @param array<string, mixed> $data spec data read from YAML or JSON
     *
     * @throws TypeErrorException in case invalid data is supplied.
     */
    public function __construct(array $data, OpenApiVersion|null $openApiVersion = null);

    public function getApiVersion(): OpenApiVersion;

    /** @return array<array-key, string|list<string>> array of attributes available in this object. */
    public function attributes(): array;

    /**
     * @return object returns the serializable data of this object for converting it
     * to JSON or YAML.
     */
    public function getSerializableData(): object;

    /**
     * Validate object data according to OpenAPI spec.
     *
     * @see getErrors()
     *
     * @return bool whether the loaded data is valid according to OpenAPI spec
     */
    public function validate(): bool;

    /**
     * @see validate()
     *
     * @return string[] list of validation errors according to OpenAPI spec.
     */
    public function getErrors(): array;

    /**
     * Resolves all Reference Objects in this object and replaces them with their resolution.
     */
    public function resolveReferences(ReferenceContext|null $context = null): void;

    /**
     * Set context for all Reference Objects in this object.
     */
    public function setReferenceContext(ReferenceContext $context): void;
}
