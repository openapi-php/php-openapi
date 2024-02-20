<?php

declare(strict_types=1);

namespace OpenApiTest;

use openapiphp\openapi\OpenApiVersion;
use openapiphp\openapi\ReferenceTarget;
use openapiphp\openapi\spec\PathItem;
use openapiphp\openapi\spec\Paths;
use openapiphp\openapi\spec\Reference;
use openapiphp\openapi\SpecObjectInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReferenceTargetTest extends TestCase
{
    #[DataProvider('allowedAttributesDataProvider')]
    public function testAllowsAttribute(SpecObjectInterface $currentSpec, string|null $targetProperty, string $allowedAttributeName): void
    {
        $context = new ReferenceTarget($currentSpec, $targetProperty);
        self::assertTrue($context->allowsAttribute($allowedAttributeName));
    }

    /** @return iterable<string, list<SpecObjectInterface|string|null>> */
    public static function allowedAttributesDataProvider(): iterable
    {
        yield 'Reference allows $ref in OpenAPI 3.0' => [
            new Reference(['$ref' => '#/foo/bar'], OpenApiVersion::VERSION_3_0),
            null,
            '$ref',
        ];

        yield 'Reference allows summary in OpenAPI 3.1' => [
            new Reference(['$ref' => '#/foo/bar'], OpenApiVersion::VERSION_3_1),
            null,
            'summary',
        ];

        yield 'Paths allows every kind of string' => [
            new Paths([], OpenApiVersion::VERSION_3_0),
            null,
            '/foobar',
        ];

        yield 'PathItem allows GET (in uppercase)' => [
            new Paths([], OpenApiVersion::VERSION_3_0),
            null,
            'GET',
        ];

        yield 'PathItem allows get (in lowercase)' => [
            new Paths([], OpenApiVersion::VERSION_3_0),
            null,
            'get',
        ];

        yield 'PathItem allows to set the description' => [
            new Paths([], OpenApiVersion::VERSION_3_0),
            'description',
            'any description',
        ];

        yield 'PathItem allows to set only allowed attributes to the get Operation' => [
            new Paths([], OpenApiVersion::VERSION_3_0),
            'get',
            'summary',
        ];
    }

    #[DataProvider('diallowedAttributesDataProvider')]
    public function testDisallowedAttribute(SpecObjectInterface $currentSpec, string|null $targetProperty, string $disallowedAttributeName): void
    {
        $context = new ReferenceTarget($currentSpec, $targetProperty);
        self::assertFalse($context->allowsAttribute($disallowedAttributeName));
    }

    /** @return iterable<string, list<SpecObjectInterface|string|null>> */
    public static function diallowedAttributesDataProvider(): iterable
    {
        yield 'Reference does not allows summary in OpenAPI 3.0' => [
            new Reference(['$ref' => '#/foo/bar'], OpenApiVersion::VERSION_3_0),
            null,
            'summary',
        ];

        yield 'PathItem does not allows connect' => [
            new PathItem([], OpenApiVersion::VERSION_3_0),
            null,
            'connect',
        ];

        yield 'PathItem does not allows to set any attribute to the get Operation' => [
            new PathItem([], OpenApiVersion::VERSION_3_0),
            'get',
            'notAllowedInOperation',
        ];
    }
}
