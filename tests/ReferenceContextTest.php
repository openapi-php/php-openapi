<?php

declare(strict_types=1);

namespace OpenApiTest;

use openapiphp\openapi\ReferenceContext;
use openapiphp\openapi\spec\OpenApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_unique;

class ReferenceContextTest extends TestCase
{
    /** @return iterable<list<string>> */
    public static function resolveUriProvider(): iterable
    {
        $data = [
            [
                'https://example.com/openapi.yaml', // base URI
                'definitions.yaml', // referenced URI
                'https://example.com/definitions.yaml', // expected result
            ],
            [
                'https://example.com/openapi.yaml', // base URI
                'definitions.yaml#/components/Pet', // referenced URI
                'https://example.com/definitions.yaml#/components/Pet', // expected result
            ],

            [
                'https://example.com/openapi.yaml', // base URI
                '/definitions.yaml', // referenced URI
                'https://example.com/definitions.yaml', // expected result
            ],
            [
                'https://example.com/openapi.yaml', // base URI
                '/definitions.yaml#/components/Pet', // referenced URI
                'https://example.com/definitions.yaml#/components/Pet', // expected result
            ],

            [
                'https://example.com/api/openapi.yaml', // base URI
                'definitions.yaml', // referenced URI
                'https://example.com/api/definitions.yaml', // expected result
            ],
            [
                'https://example.com/api/openapi.yaml', // base URI
                'definitions.yaml#/components/Pet', // referenced URI
                'https://example.com/api/definitions.yaml#/components/Pet', // expected result
            ],

            [
                'https://example.com/api/openapi.yaml', // base URI
                '/definitions.yaml', // referenced URI
                'https://example.com/definitions.yaml', // expected result
            ],
            [
                'https://example.com/api/openapi.yaml', // base URI
                '/definitions.yaml#/components/Pet', // referenced URI
                'https://example.com/definitions.yaml#/components/Pet', // expected result
            ],

            [
                'https://example.com/api/openapi.yaml', // base URI
                '../definitions.yaml', // referenced URI
                'https://example.com/definitions.yaml', // expected result
            ],
            [
                'https://example.com/api/openapi.yaml', // base URI
                '../definitions.yaml#/components/Pet', // referenced URI
                'https://example.com/definitions.yaml#/components/Pet', // expected result
            ],

            [
                '/var/www/openapi.yaml', // base URI
                'definitions.yaml', // referenced URI
                'file:///var/www/definitions.yaml', // expected result
            ],
            [
                '/var/www/openapi.yaml', // base URI
                'definitions.yaml#/components/Pet', // referenced URI
                'file:///var/www/definitions.yaml#/components/Pet', // expected result
            ],

            [
                '/var/www/openapi.yaml', // base URI
                '/var/definitions.yaml', // referenced URI
                'file:///var/definitions.yaml', // expected result
            ],
            [
                '/var/www/openapi.yaml', // base URI
                '/var/definitions.yaml#/components/Pet', // referenced URI
                'file:///var/definitions.yaml#/components/Pet', // expected result
            ],
        ];

        // absolute URLs should not be changed
        foreach (array_unique(array_map('current', $data)) as $url) {
            $data[] = [
                $url,
                'file:///var/www/definitions.yaml',
                'file:///var/www/definitions.yaml',
            ];
            $data[] = [
                $url,
                'file:///var/www/definitions.yaml#/components/Pet',
                'file:///var/www/definitions.yaml#/components/Pet',
            ];

            $data[] = [
                $url,
                'https://example.com/definitions.yaml',
                'https://example.com/definitions.yaml',
            ];
            $data[] = [
                $url,
                'https://example.com/definitions.yaml#/components/Pet',
                'https://example.com/definitions.yaml#/components/Pet',
            ];
        }

        return $data;
    }

    #[DataProvider('resolveUriProvider')]
    public function testResolveUri(string $baseUri, string $referencedUri, string $expected): void
    {
        $context = new ReferenceContext(new OpenApi([]), $baseUri);
        $this->assertEquals($expected, $context->resolveRelativeUri($referencedUri));
    }

    /** @return iterable<list<string>> */
    public static function normalizeUriProvider(): iterable
    {
        return [
            [
                'https://example.com/openapi.yaml',
                'https://example.com/openapi.yaml',
            ],
            [
                'https://example.com/openapi.yaml#/components/Pet',
                'https://example.com/openapi.yaml#/components/Pet',
            ],
            [
                'https://example.com/./openapi.yaml',
                'https://example.com/openapi.yaml',
            ],
            [
                'https://example.com/./openapi.yaml#/components/Pet',
                'https://example.com/openapi.yaml#/components/Pet',
            ],
            [
                'https://example.com/api/../openapi.yaml',
                'https://example.com/openapi.yaml',
            ],
            [
                'https://example.com/api/../openapi.yaml#/components/Pet',
                'https://example.com/openapi.yaml#/components/Pet',
            ],
            [
                'https://example.com/../openapi.yaml',
                'https://example.com/../openapi.yaml',
            ],
            [
                'https://example.com/../openapi.yaml#/components/Pet',
                'https://example.com/../openapi.yaml#/components/Pet',
            ],
            [
                '/definitions.yaml',
                'file:///definitions.yaml',
            ],
            [
                '/definitions.yaml#/components/Pet',
                'file:///definitions.yaml#/components/Pet',
            ],
            [
                '/var/www/definitions.yaml',
                'file:///var/www/definitions.yaml',
            ],
            [
                '/var/www/definitions.yaml#/components/Pet',
                'file:///var/www/definitions.yaml#/components/Pet',
            ],
            [
                '/var/www/api/../definitions.yaml',
                'file:///var/www/definitions.yaml',
            ],
            [
                '/var/www/api/../definitions.yaml#/components/Pet',
                'file:///var/www/definitions.yaml#/components/Pet',
            ],
            [
                '/var/www/api/foo/../../definitions.yaml#/components/Pet',
                'file:///var/www/definitions.yaml#/components/Pet',
            ],
        ];
    }

    #[DataProvider('normalizeUriProvider')]
    public function testNormalizeUri(string $uri, string $expected): void
    {
        $context = new ReferenceContext(null, $uri);
        $this->assertEquals($expected, $context->getUri());
    }
}
