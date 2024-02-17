<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\exceptions\TypeErrorException;
use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\OpenApi;
use openapiphp\openapi\spec\Operation;
use openapiphp\openapi\spec\PathItem;
use openapiphp\openapi\spec\Paths;
use openapiphp\openapi\spec\Reference;
use openapiphp\openapi\spec\Response;
use openapiphp\openapi\spec\Responses;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use function assert;
use function print_r;

#[CoversClass(Paths::class)]
#[CoversClass(PathItem::class)]
class PathTest extends TestCase
{
    public function testRead(): void
    {
        $paths = Reader::readFromJson(<<<'JSON'
{
  "/pets": {
    "get": {
      "description": "Returns all pets from the system that the user has access to",
      "responses": {
        "200": {
          "description": "A list of pets.",
          "content": {
            "application/json": {
              "schema": {
                "type": "array",
                "items": {
                  "$ref": "#/components/schemas/pet"
                }
              }
            }
          }
        }
      }
    }
  }
}
JSON
            , Paths::class);
        assert($paths instanceof Paths);

        $result = $paths->validate();
        $this->assertEquals([], $paths->getErrors());
        $this->assertTrue($result);

        $this->assertTrue($paths->hasPath('/pets'));
        $this->assertTrue(isset($paths['/pets']));
        $this->assertFalse($paths->hasPath('/dog'));
        $this->assertFalse(isset($paths['/dog']));

        $this->assertInstanceOf(PathItem::class, $paths->getPath('/pets'));
        $this->assertInstanceOf(PathItem::class, $paths['/pets']);
        $this->assertInstanceOf(Operation::class, $paths->getPath('/pets')->get);
        $this->assertNull($paths->getPath('/dog'));
        $this->assertNull($paths['/dog']);

        $this->assertCount(1, $paths->getPaths());
        $this->assertCount(1, $paths);
        foreach ($paths as $path => $pathItem) {
            $this->assertEquals('/pets', $path);
            $this->assertInstanceOf(PathItem::class, $pathItem);
        }
    }

    public function testCreationFromObjects(): void
    {
        $paths = new Paths([
            '/pets' => new PathItem([
                'get' => new Operation([
                    'responses' => new Responses([
                        200 => new Response(['description' => 'A list of pets.']),
                        404 => ['description' => 'The pets list is gone 🙀'],
                    ]),
                ]),
            ]),
        ]);

        $this->assertTrue($paths->hasPath('/pets'));
        $this->assertInstanceOf(PathItem::class, $paths->getPath('/pets'));
        $this->assertInstanceOf(PathItem::class, $paths['/pets']);
        $this->assertInstanceOf(Operation::class, $paths->getPath('/pets')->get);

        $this->assertSame('A list of pets.', $paths->getPath('/pets')->get->responses->getResponse('200')->description);
        $this->assertSame('The pets list is gone 🙀', $paths->getPath('/pets')->get->responses->getResponse('404')->description);
    }

    /** @return iterable<array<string, negative-int>|string> */
    public static function badPathsConfigProvider(): iterable
    {
        yield [['/pets' => 'foo'], 'Path MUST be either array or PathItem object, "string" given'];
        yield [['/pets' => 42], 'Path MUST be either array or PathItem object, "integer" given'];
        yield [['/pets' => false], 'Path MUST be either array or PathItem object, "boolean" given'];
        yield [['/pets' => new stdClass()], 'Path MUST be either array or PathItem object, "stdClass" given'];
        // The last one can be supported in future, but now SpecBaseObjects::__construct() requires array explicitly
    }

    /** @param (PathItem|array|null)[] $config */
    #[DataProvider('badPathsConfigProvider')]
    public function testPathsCanNotBeCreatedFromBullshit(array $config, string $expectedException): void
    {
        $this->expectException(TypeErrorException::class);
        $this->expectExceptionMessage($expectedException);

        new Paths($config);
    }

    public function testInvalidPath(): void
    {
        $paths = Reader::readFromJson(<<<'JSON'
{
  "pets": {
    "get": {
      "description": "Returns all pets from the system that the user has access to",
      "responses": {
        "200": {
          "description": "A list of pets."
        }
      }
    }
  }
}
JSON
            , Paths::class);
        assert($paths instanceof Paths);

        $result = $paths->validate();
        $this->assertEquals(['Path must begin with /: pets'], $paths->getErrors());
        $this->assertFalse($result);
    }

    public function testPathItemReference(): void
    {
        $file    = __DIR__ . '/data/paths/openapi.yaml';
        $openapi = Reader::readFromYamlFile($file, OpenApi::class, false);
        assert($openapi instanceof OpenApi);

        $result = $openapi->validate();
        $this->assertEquals([], $openapi->getErrors(), print_r($openapi->getErrors(), true));
        $this->assertTrue($result);

        $this->assertInstanceOf(Paths::class, $openapi->paths);
        $this->assertInstanceOf(PathItem::class, $fooPath = $openapi->paths['/foo']);
        $this->assertInstanceOf(PathItem::class, $barPath = $openapi->paths['/bar']);
        $this->assertSame([
            'x-extension-1' => 'Extension1',
            'x-extension-2' => 'Extension2',
        ], $openapi->getExtensions());

        $this->assertEmpty($fooPath->getOperations());
        $this->assertEmpty($barPath->getOperations());

        $this->assertInstanceOf(Reference::class, $fooPath->getReference());
        $this->assertInstanceOf(Reference::class, $barPath->getReference());

        $this->assertNull($fooPath->getReference()->resolve());
        $this->assertInstanceOf(PathItem::class, $referencedBarPath = $barPath->getReference()->resolve());

        $this->assertCount(1, $referencedBarPath->getOperations());
        $this->assertInstanceOf(Operation::class, $referencedBarPath->get);
        $this->assertEquals('getBar', $referencedBarPath->get->operationId);

        $this->assertInstanceOf(Reference::class, $reference200 = $referencedBarPath->get->responses['200']);
        $this->assertInstanceOf(Response::class, $referencedBarPath->get->responses['404']);
        $this->assertEquals('non-existing resource', $referencedBarPath->get->responses['404']->description);

        $path200 = $reference200->resolve();
        $this->assertInstanceOf(Response::class, $path200);
        $this->assertEquals('A bar', $path200->description);

        $openapi = Reader::readFromYamlFile($file, OpenApi::class, true);
        assert($openapi instanceof OpenApi);

        $result = $openapi->validate();
        $this->assertEquals([], $openapi->getErrors(), print_r($openapi->getErrors(), true));
        $this->assertTrue($result);

        $this->assertInstanceOf(Paths::class, $openapi->paths);
        $this->assertInstanceOf(PathItem::class, $fooPath = $openapi->paths['/foo']);
        $this->assertInstanceOf(PathItem::class, $barPath = $openapi->paths['/bar']);

        $this->assertEmpty($fooPath->getOperations());
        $this->assertCount(1, $barPath->getOperations());
        $this->assertInstanceOf(Operation::class, $barPath->get);
        $this->assertEquals('getBar', $barPath->get->operationId);

        $this->assertEquals('A bar', $barPath->get->responses['200']->description);
        $this->assertEquals('non-existing resource', $barPath->get->responses['404']->description);
    }
}
