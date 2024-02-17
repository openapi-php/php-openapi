<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\exceptions\TypeErrorException;
use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\MediaType;
use openapiphp\openapi\spec\Reference;
use openapiphp\openapi\spec\Response;
use openapiphp\openapi\spec\Responses;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use function assert;

#[CoversClass(Response::class)]
#[CoversClass(Responses::class)]
class ResponseTest extends TestCase
{
    public function testRead(): void
    {
        $response = Reader::readFromJson(<<<'JSON'
{
  "description": "A complex object array response",
  "content": {
    "application/json": {
      "schema": {
        "type": "array",
        "items": {
          "$ref": "#/components/schemas/VeryComplexType"
        }
      }
    }
  }
}
JSON
            , Response::class);
        assert($response instanceof Response);

        $result = $response->validate();
        $this->assertEquals([], $response->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('A complex object array response', $response->description);
        $this->assertArrayHasKey('application/json', $response->content);
        $this->assertInstanceOf(MediaType::class, $response->content['application/json']);

        $response = Reader::readFromJson(<<<'JSON'
{
  "content": {
    "application/json": {
      "schema": {
        "type": "array",
        "items": {
          "$ref": "#/components/schemas/VeryComplexType"
        }
      }
    }
  }
}
JSON
            , Response::class);
        assert($response instanceof Response);

        $result = $response->validate();
        $this->assertEquals(['Response is missing required property: description'], $response->getErrors());
        $this->assertFalse($result);
    }

    public function testResponses(): void
    {
        $responses = Reader::readFromYaml(<<<'YAML'
'200':
  description: a pet to be returned
  content:
    application/json:
      schema:
        $ref: '#/components/schemas/Pet'
default:
  description: Unexpected error
  content:
    application/json:
      schema:
        $ref: '#/components/schemas/ErrorModel'
YAML
            , Responses::class);
        assert($responses instanceof Responses);

        $result = $responses->validate();
        $this->assertEquals([], $responses->getErrors());
        $this->assertTrue($result);

        $this->assertTrue($responses->hasResponse('200'));
        $this->assertFalse($responses->hasResponse('201'));
        $this->assertTrue($responses->hasResponse('default'));
        $this->assertTrue(isset($responses['200']));
        $this->assertFalse(isset($responses['201']));
        $this->assertTrue(isset($responses['default']));

        $this->assertCount(2, $responses->getResponses());
        $this->assertCount(2, $responses);
        $this->assertInstanceOf(Response::class, $responses->getResponses()['200']);
        $this->assertInstanceOf(Response::class, $responses->getResponses()['default']);

        $this->assertInstanceOf(Response::class, $responses->getResponse('200'));
        $this->assertInstanceOf(Response::class, $responses->getResponse('default'));
        $this->assertNull($responses->getResponse('201'));
        $this->assertInstanceOf(Response::class, $responses['200']);
        $this->assertInstanceOf(Response::class, $responses['default']);
        $this->assertNull($responses['201']);

        $this->assertEquals('a pet to be returned', $responses->getResponse('200')->description);
        $this->assertEquals('a pet to be returned', $responses['200']->description);

        $keys = [];
        foreach ($responses as $k => $response) {
            $keys[] = $k;
            $this->assertInstanceOf(Response::class, $response);
        }

        $this->assertEquals([200, 'default'], $keys);
    }

    public function testResponseCodes(): void
    {
        $responses = Reader::readFromYaml(<<<'YAML'
'200':
  description: valid statuscode
'99':
  description: invalid statuscode
'302':
  description: valid statuscode
'401':
  description: valid statuscode
'601':
  description: invalid statuscode
'6X1':
  description: invalid statuscode
'2X1':
  description: invalid statuscode
'2XX':
  description: valid statuscode
'default':
  description: valid statuscode
'example':
  description: valid statuscode
YAML
            , Responses::class);
        assert($responses instanceof Responses);

        $result = $responses->validate();
        $this->assertEquals([
            'Responses: 99 is not a valid HTTP status code.',
            'Responses: 601 is not a valid HTTP status code.',
            'Responses: 6X1 is not a valid HTTP status code.',
            'Responses: 2X1 is not a valid HTTP status code.',
            'Responses: example is not a valid HTTP status code.',

        ], $responses->getErrors());
        $this->assertFalse($result);
    }

    public function testCreateionFromObjects(): void
    {
        $responses = new Responses([
            200 => new Response(['description' => 'A list of pets.']),
            404 => ['description' => 'The pets list is gone 🙀'],
        ]);

        $this->assertSame('A list of pets.', $responses->getResponse('200')->description);
        $this->assertSame('The pets list is gone 🙀', $responses->getResponse('404')->description);
    }

    /** @return iterable<array<string, mixed>|string> */
    public static function badResponseProvider(): iterable
    {
        yield [['200' => 'foo'], 'Response MUST be either an array, a Response or a Reference object, "string" given'];
        yield [['200' => 42], 'Response MUST be either an array, a Response or a Reference object, "integer" given'];
        yield [['200' => false], 'Response MUST be either an array, a Response or a Reference object, "boolean" given'];
        yield [['200' => new stdClass()], 'Response MUST be either an array, a Response or a Reference object, "stdClass" given'];
        // The last one can be supported in future, but now SpecBaseObjects::__construct() requires array explicitly
    }

    /** @param Response[]|Reference[]|array[] $config */
    #[DataProvider('badResponseProvider')]
    public function testPathsCanNotBeCreatedFromBullshit(array $config, string $expectedException): void
    {
        $this->expectException(TypeErrorException::class);
        $this->expectExceptionMessage($expectedException);

        new Responses($config);
    }
}
