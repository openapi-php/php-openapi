<?php

declare(strict_types=1);

namespace openapiphp\openapi;

use function array_is_list;
use function array_key_exists;
use function get_class;
use function is_array;
use function is_string;
use function is_subclass_of;

/**
 * Add the "parent" Spec with the changed attribute as Reference context
 * This will be used to check if the resolved $ref contains only allowed attributes for the object containing the $ref
 */
class ReferenceTarget
{
    public function __construct(
        public readonly SpecObjectInterface $currentSpec,
        public readonly string|null $targetProperty = null,
    ) {
    }

    public function asString(): string
    {
        return get_class($this->currentSpec) . $this->targetProperty;
    }

    public function createInstance(mixed $data): SpecObjectInterface|null
    {
        $targetSpec = $this->currentSpec->attributes()[$this->targetProperty] ?? get_class($this->currentSpec);
        if (is_string($targetSpec) && is_subclass_of($targetSpec, SpecObjectInterface::class)) {
            return new $targetSpec(
                is_array($data) ? $data : [],
                $this->currentSpec->getApiVersion(),
            );
        }

        return null;
    }

    public function allowsAttribute(string $attributeName): bool
    {
        $constructorArgs = (array) $this->currentSpec->getSerializableData();
        $constructorArgs = $constructorArgs[$this->targetProperty] ?? $constructorArgs;
        $target          = $this->createInstance($constructorArgs);

        if ($target !== null) {
            $targetAttributes = $target->attributes();
            // if there aren't any properties defined, every attribute is allowed
            if (array_is_list($targetAttributes)) {
                return true;
            }

            return array_key_exists($attributeName, $target->attributes());
        }

        return false;
    }
}
