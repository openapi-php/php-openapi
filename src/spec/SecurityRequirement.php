<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

/**
 * Lists the required security schemes to execute this operation.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#securityRequirementObject
 */
final class SecurityRequirement extends SpecBaseObject
{
    /** @inheritDoc */
    public function attributes(): array
    {
        // this object does not have a fixed set of attribute names
        return [];
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     *
     * Call `addError()` in case of validation errors.
     */
    protected function performValidation(): void
    {
    }
}
