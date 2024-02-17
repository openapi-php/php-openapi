<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\Callback;
use openapiphp\openapi\spec\Components;
use openapiphp\openapi\spec\Example;
use openapiphp\openapi\spec\Header;
use openapiphp\openapi\spec\Link;
use openapiphp\openapi\spec\Parameter;
use openapiphp\openapi\spec\RequestBody;
use openapiphp\openapi\spec\Response;
use openapiphp\openapi\spec\Schema;
use openapiphp\openapi\spec\SecurityScheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;
use function sprintf;

#[CoversClass(Components::class)]
class ComponentsTest extends TestCase
{
    public function testRead(): void
    {
        $components = Reader::readFromYaml(<<<'YAML'
schemas:
  GeneralError:
    type: object
    properties:
      code:
        type: integer
        format: int32
      message:
        type: string
  Category:
    type: object
    properties:
      id:
        type: integer
        format: int64
      name:
        type: string
  Tag:
    type: object
    properties:
      id:
        type: integer
        format: int64
      name:
        type: string
parameters:
  skipParam:
    name: skip
    in: query
    description: number of items to skip
    required: true
    schema:
      type: integer
      format: int32
  limitParam:
    name: limit
    in: query
    description: max records to return
    required: true
    schema:
      type: integer
      format: int32
responses:
  NotFound:
    description: Entity not found.
  IllegalInput:
    description: Illegal input for operation.
  GeneralError:
    description: General Error
    content:
      application/json:
        schema:
          $ref: '#/components/schemas/GeneralError'
securitySchemes:
  api_key:
    type: apiKey
    name: api_key
    in: header
  petstore_auth:
    type: oauth2
    flows: 
      implicit:
        authorizationUrl: http://example.org/api/oauth/dialog
        scopes:
          write:pets: modify pets in your account
          read:pets: read your pets
YAML
            , Components::class);
        assert($components instanceof Components);

        $result = $components->validate();
        $this->assertEquals([], $components->getErrors());
        $this->assertTrue($result);

        $this->assertAllInstanceOf(Schema::class, $components->schemas);
        $this->assertCount(3, $components->schemas);
        $this->assertArrayHasKey('GeneralError', $components->schemas);
        $this->assertArrayHasKey('Category', $components->schemas);
        $this->assertArrayHasKey('Tag', $components->schemas);
        $this->assertAllInstanceOf(Response::class, $components->responses);
        $this->assertCount(3, $components->responses);
        $this->assertArrayHasKey('NotFound', $components->responses);
        $this->assertArrayHasKey('IllegalInput', $components->responses);
        $this->assertArrayHasKey('GeneralError', $components->responses);
        $this->assertAllInstanceOf(Parameter::class, $components->parameters);
        $this->assertCount(2, $components->parameters);
        $this->assertArrayHasKey('skipParam', $components->parameters);
        $this->assertArrayHasKey('limitParam', $components->parameters);
        $this->assertAllInstanceOf(Example::class, $components->examples);
        $this->assertCount(0, $components->examples); // TODO
        $this->assertAllInstanceOf(RequestBody::class, $components->requestBodies);
        $this->assertCount(0, $components->requestBodies); // TODO
        $this->assertAllInstanceOf(Header::class, $components->headers);
        $this->assertCount(0, $components->headers); // TODO
        $this->assertAllInstanceOf(SecurityScheme::class, $components->securitySchemes);
        $this->assertCount(2, $components->securitySchemes);
        $this->assertArrayHasKey('api_key', $components->securitySchemes);
        $this->assertArrayHasKey('petstore_auth', $components->securitySchemes);
        $this->assertAllInstanceOf(Link::class, $components->links);
        $this->assertCount(0, $components->links); // TODO
        $this->assertAllInstanceOf(Callback::class, $components->callbacks);
        $this->assertCount(0, $components->callbacks); // TODO
    }

    /**
     * @param class-string          $className
     * @param array<string, string> $array
     */
    public function assertAllInstanceOf(string $className, array $array): void
    {
        foreach ($array as $k => $v) {
            $this->assertInstanceOf($className, $v, sprintf('Asserting that item with key \'%s\' is instance of %s', $k, $className));
        }
    }
}
