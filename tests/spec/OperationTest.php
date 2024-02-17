<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\ExternalDocumentation;
use openapiphp\openapi\spec\Operation;
use openapiphp\openapi\spec\Parameter;
use openapiphp\openapi\spec\RequestBody;
use openapiphp\openapi\spec\Responses;
use openapiphp\openapi\spec\SecurityRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;

#[CoversClass(Operation::class)]
#[CoversClass(ExternalDocumentation::class)]
class OperationTest extends TestCase
{
    public function testRead(): void
    {
        $operation = Reader::readFromYaml(<<<'YAML'
tags:
- pet
summary: Updates a pet in the store with form data
operationId: updatePetWithForm
parameters:
- name: petId
  in: path
  description: ID of pet that needs to be updated
  required: true
  schema:
    type: string
requestBody:
  content:
    'application/x-www-form-urlencoded':
      schema:
       properties:
          name: 
            description: Updated name of the pet
            type: string
          status:
            description: Updated status of the pet
            type: string
       required:
         - status
responses:
  '200':
    description: Pet updated.
    content: 
      'application/json': {}
      'application/xml': {}
  '405':
    description: Method Not Allowed
    content: 
      'application/json': {}
      'application/xml': {}
security:
- petstore_auth:
  - write:pets
  - read:pets
externalDocs:
  description: Find more info here
  url: https://example.com
YAML
            , Operation::class);
        assert($operation instanceof Operation);

        $result = $operation->validate();
        $this->assertEquals([], $operation->getErrors());
        $this->assertTrue($result);

        $this->assertCount(1, $operation->tags);
        $this->assertEquals(['pet'], $operation->tags);

        $this->assertEquals('Updates a pet in the store with form data', $operation->summary);
        $this->assertEquals('updatePetWithForm', $operation->operationId);

        $this->assertCount(1, $operation->parameters);
        $this->assertInstanceOf(Parameter::class, $operation->parameters[0]);
        $this->assertEquals('petId', $operation->parameters[0]->name);

        $this->assertInstanceOf(RequestBody::class, $operation->requestBody);
        $this->assertCount(1, $operation->requestBody->content);
        $this->assertArrayHasKey('application/x-www-form-urlencoded', $operation->requestBody->content);

        $this->assertInstanceOf(Responses::class, $operation->responses);

        $this->assertCount(1, $operation->security);
        $this->assertInstanceOf(SecurityRequirement::class, $operation->security[0]);
        $this->assertCount(2, $operation->security[0]->petstore_auth);
        $this->assertEquals(['write:pets', 'read:pets'], $operation->security[0]->petstore_auth);

        $this->assertInstanceOf(ExternalDocumentation::class, $operation->externalDocs);
        $this->assertEquals('Find more info here', $operation->externalDocs->description);
        $this->assertEquals('https://example.com', $operation->externalDocs->url);

        // deprecated Default value is false.
        $this->assertFalse($operation->deprecated);
    }
}
