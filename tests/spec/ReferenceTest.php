<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\exceptions\UnresolvableReferenceException;
use openapiphp\openapi\OpenApiVersion;
use openapiphp\openapi\Reader;
use openapiphp\openapi\ReferenceContext;
use openapiphp\openapi\spec\Example;
use openapiphp\openapi\spec\OpenApi;
use openapiphp\openapi\spec\Parameter;
use openapiphp\openapi\spec\Reference;
use openapiphp\openapi\spec\RequestBody;
use openapiphp\openapi\spec\Response;
use openapiphp\openapi\spec\Schema;
use openapiphp\openapi\Writer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;
use function dirname;
use function file_get_contents;
use function preg_replace;
use function reset;
use function str_replace;
use function stripos;
use function strtr;

use const PHP_OS;

#[CoversClass(Reference::class)]
class ReferenceTest extends TestCase
{
    public function testResolveInDocument(): void
    {
        $openapi = Reader::readFromYaml(<<<'YAML'
openapi: 3.0.0
info:
  title: test api
  version: 1.0.0
components:
  schemas:
    Pet:
      type: object
      properties:
        id:
          type: integer
  examples:
    frog-example:
      description: a frog
  responses:
    Pet:
      description: returns a pet
paths:
  '/pet':
    get:
      responses:
        200:
          description: return a pet
          content:
            'application/json':
              schema:
                $ref: "#/components/schemas/Pet"
              examples:
                frog:
                  $ref: "#/components/examples/frog-example"
  '/pet/1':
    get:
      responses:
        200:
          $ref: "#/components/responses/Pet"
YAML
            , OpenApi::class);
        assert($openapi instanceof OpenApi);

        $result = $openapi->validate();
        $this->assertEquals([], $openapi->getErrors());
        $this->assertTrue($result);

        $petResponse = $openapi->paths->getPath('/pet')->get->responses['200'];
        assert($petResponse instanceof Response);
        $this->assertInstanceOf(Reference::class, $petResponse->content['application/json']->schema);
        $this->assertInstanceOf(Reference::class, $petResponse->content['application/json']->examples['frog']);
        $this->assertInstanceOf(Reference::class, $openapi->paths->getPath('/pet/1')->get->responses['200']);

        $openapi->resolveReferences(new ReferenceContext($openapi, 'file:///tmp/openapi.yaml'));

        $this->assertInstanceOf(Schema::class, $refSchema = $petResponse->content['application/json']->schema);
        $this->assertInstanceOf(Example::class, $refExample = $petResponse->content['application/json']->examples['frog']);
        $this->assertInstanceOf(Response::class, $refResponse = $openapi->paths->getPath('/pet/1')->get->responses['200']);

        $this->assertEquals($openapi->components->schemas['Pet'], $refSchema);
        $this->assertEquals($openapi->components->examples['frog-example'], $refExample);
        $this->assertEquals($openapi->components->responses['Pet'], $refResponse);
    }

    public function testResolveCyclicReferenceInDocument(): void
    {
        $openapi = Reader::readFromYaml(<<<'YAML'
            openapi: 3.0.0
            info:
              title: test api
              version: 1.0.0
            components:
              schemas:
                Pet:
                  type: object
                  properties:
                    id:
                      type: array
                      items:
                        $ref: "#/components/schemas/Pet"
                  example:
                    $ref: "#/components/examples/frog-example"
              examples:
                frog-example:
                  description: a frog
            paths:
              '/pet':
                get:
                  responses:
                    200:
                      description: return a pet
                      content:
                        'application/json':
                          schema:
                            $ref: "#/components/schemas/Pet"
                          examples:
                            frog:
                              $ref: "#/components/examples/frog-example"
            YAML
            , OpenApi::class);
        assert($openapi instanceof OpenApi);

        $result = $openapi->validate();
        $this->assertEquals([], $openapi->getErrors());
        $this->assertTrue($result);

        $response = $openapi->paths->getPath('/pet')->get->responses['200'];
        assert($response instanceof Response);
        $this->assertInstanceOf(Reference::class, $response->content['application/json']->schema);
        $this->assertInstanceOf(Reference::class, $response->content['application/json']->examples['frog']);

        //        $this->expectException(\openapiphp\openapi\\exceptions\UnresolvableReferenceException::class);
        $openapi->resolveReferences(new ReferenceContext($openapi, 'file:///tmp/openapi.yaml'));

        $this->assertInstanceOf(Schema::class, $petItems = $openapi->components->schemas['Pet']->properties['id']->items);
        $this->assertInstanceOf(Schema::class, $refSchema = $response->content['application/json']->schema);
        $this->assertInstanceOf(Example::class, $refExample = $response->content['application/json']->examples['frog']);

        $this->assertEquals($openapi->components->schemas['Pet'], $petItems);
        $this->assertEquals($openapi->components->schemas['Pet'], $refSchema);
        $this->assertEquals($openapi->components->examples['frog-example'], $refExample);
    }

    private function createFileUri(string $file): string
    {
        if (stripos(PHP_OS, 'WIN') === 0) {
            return 'file:///' . strtr($file, [' ' => '%20', '\\' => '/']);
        }

        return 'file://' . $file;
    }

    public function testResolveFile(): void
    {
        $file = __DIR__ . '/data/reference/base.yaml';
        $yaml = str_replace('##ABSOLUTEPATH##', $this->createFileUri(dirname($file)), file_get_contents($file));

        $openapi = Reader::readFromYaml($yaml);
        assert($openapi instanceof OpenApi);

        $result = $openapi->validate();
        $this->assertEquals([], $openapi->getErrors());
        $this->assertTrue($result);

        $this->assertInstanceOf(Reference::class, $petItems = $openapi->components->schemas['Pet']);
        $this->assertInstanceOf(Reference::class, $petItems = $openapi->components->schemas['Dog']);

        $openapi->resolveReferences(new ReferenceContext($openapi, $file));

        $this->assertInstanceOf(Schema::class, $petItems = $openapi->components->schemas['Pet']);
        $this->assertInstanceOf(Schema::class, $petItems = $openapi->components->schemas['Dog']);
        $this->assertArrayHasKey('id', $openapi->components->schemas['Pet']->properties);
        $this->assertArrayHasKey('name', $openapi->components->schemas['Dog']->properties);

        // second level reference inside of definitions.yaml
        $this->assertArrayHasKey('food', $openapi->components->schemas['Dog']->properties);
        $this->assertInstanceOf(Schema::class, $openapi->components->schemas['Dog']->properties['food']);
        $this->assertArrayHasKey('id', $openapi->components->schemas['Dog']->properties['food']->properties);
        $this->assertArrayHasKey('name', $openapi->components->schemas['Dog']->properties['food']->properties);
        $this->assertEquals(1, $openapi->components->schemas['Dog']->properties['food']->properties['id']->example);
    }

    public function testResolveFileInSubdir(): void
    {
        $file    = __DIR__ . '/data/reference/subdir.yaml';
        $openapi = Reader::readFromYamlFile($file, OpenApi::class, false);
        assert($openapi instanceof OpenApi);

        $result = $openapi->validate();
        $this->assertEquals([], $openapi->getErrors());
        $this->assertTrue($result);

        $this->assertInstanceOf(Reference::class, $openapi->components->schemas['Pet']);
        $this->assertInstanceOf(Reference::class, $openapi->components->schemas['Dog']);
        $this->assertInstanceOf(Reference::class, $openapi->components->parameters['Parameter.PetId']);

        $openapi->resolveReferences(new ReferenceContext($openapi, $file));

        $this->assertInstanceOf(Schema::class, $openapi->components->schemas['Pet']);
        $this->assertInstanceOf(Schema::class, $openapi->components->schemas['Dog']);
        $this->assertInstanceOf(Parameter::class, $openapi->components->parameters['Parameter.PetId']);
        $this->assertArrayHasKey('id', $openapi->components->schemas['Pet']->properties);
        $this->assertArrayHasKey('name', $openapi->components->schemas['Dog']->properties);
        $this->assertEquals('petId', $openapi->components->parameters['Parameter.PetId']->name);
        $this->assertInstanceOf(Schema::class, $openapi->components->parameters['Parameter.PetId']->schema);
        $this->assertEquals('integer', $openapi->components->parameters['Parameter.PetId']->schema->type);

        // second level references
        $this->assertArrayHasKey('food', $openapi->components->schemas['Dog']->properties);
        $this->assertInstanceOf(Schema::class, $openapi->components->schemas['Dog']->properties['food']);
        $this->assertArrayHasKey('id', $openapi->components->schemas['Dog']->properties['food']->properties);
        $this->assertArrayHasKey('name', $openapi->components->schemas['Dog']->properties['food']->properties);
        $this->assertEquals(1, $openapi->components->schemas['Dog']->properties['food']->properties['id']->example);

        $this->assertEquals('return a pet', $openapi->paths->getPath('/pets')->get->responses['200']->description);
        $responseContent = $openapi->paths->getPath('/pets')->get->responses['200']->content['application/json'];
        $this->assertInstanceOf(Schema::class, $responseContent->schema);
        $this->assertEquals('A Pet', $responseContent->schema->description);

        // third level reference back to original file
        $this->assertCount(1, $parameters = $openapi->paths->getPath('/pets')->get->parameters);
        $parameter = reset($parameters);
        $this->assertEquals('petId', $parameter->name);
        $this->assertInstanceOf(Schema::class, $parameter->schema);
        $this->assertEquals('integer', $parameter->schema->type);
    }

    public function testResolveFileInSubdirWithMultipleRelativePaths(): void
    {
        $file    = __DIR__ . '/data/reference/InlineRelativeResolve/sub/dir/Pathfile.json';
        $openapi = Reader::readFromJsonFile($file, OpenApi::class, true);
        assert($openapi instanceof OpenApi);

        $result = $openapi->validate();
        $this->assertEmpty($openapi->getErrors());
        $this->assertTrue($result);
    }

    public function testResolveFileHttp(): void
    {
        $file    = 'https://raw.githubusercontent.com/cebe/php-openapi/290389bbd337cf4d70ecedfd3a3d886715e19552/tests/spec/data/reference/base.yaml';
        $openapi = Reader::readFromYaml(str_replace('##ABSOLUTEPATH##', dirname($file), file_get_contents($file)));
        assert($openapi instanceof OpenApi);

        $result = $openapi->validate();
        $this->assertEquals([], $openapi->getErrors());
        $this->assertTrue($result);

        $this->assertInstanceOf(Reference::class, $petItems = $openapi->components->schemas['Pet']);
        $this->assertInstanceOf(Reference::class, $petItems = $openapi->components->schemas['Dog']);

        $openapi->resolveReferences(new ReferenceContext($openapi, $file));

        $this->assertInstanceOf(Schema::class, $petItems = $openapi->components->schemas['Pet']);
        $this->assertInstanceOf(Schema::class, $petItems = $openapi->components->schemas['Dog']);
        $this->assertArrayHasKey('id', $openapi->components->schemas['Pet']->properties);
        $this->assertArrayHasKey('name', $openapi->components->schemas['Dog']->properties);
    }

    public function testResolvePaths(): void
    {
        $openapi = Reader::readFromJsonFile(__DIR__ . '/data/reference/playlist.json', OpenApi::class, false);
        assert($openapi instanceof OpenApi);

        $result = $openapi->validate();
        $this->assertEquals([], $openapi->getErrors());
        $this->assertTrue($result);

        $playlistsBody = $openapi->paths['/playlist']->post->requestBody;
        $playlistBody  = $openapi->paths['/playlist/{id}']->patch->requestBody;

        $this->assertInstanceOf(RequestBody::class, $playlistsBody);
        $this->assertInstanceOf(Reference::class, $playlistBody);

        $openapi->resolveReferences();

        $newPlaylistBody = $openapi->paths['/playlist/{id}']->patch->requestBody;
        $this->assertInstanceOf(RequestBody::class, $playlistsBody);
        $this->assertInstanceOf(RequestBody::class, $newPlaylistBody);
        $this->assertEquals($playlistsBody, $newPlaylistBody);
    }

    public function testReferenceToArray(): void
    {
        $schema  = <<<'YAML'
        openapi: 3.0.0
        components:
          schemas:
            Pet:
              type: object
              properties:
                id:
                  type: integer
                typeA:
                  type: string
                  enum:
                    - "One"
                    - "Two"
                typeB:
                  type: string
                  enum:
                    $ref: '#/components/schemas/Pet/properties/typeA/enum'
                typeC:
                  type: string
                  enum:
                    - "Three"
                    - $ref: '#/components/schemas/Pet/properties/typeA/enum/1'
                typeD:
                  type: string
                  enum:
                    $ref: 'definitions.yaml#/Dog/properties/typeD/enum'
                typeE:
                  type: string
                  enum:
                    - $ref: 'definitions.yaml#/Dog/properties/typeD/enum/1'
                    - "Six"
        
        YAML;
        $openapi = Reader::readFromYaml($schema);
        $openapi->resolveReferences(new ReferenceContext($openapi, $this->createFileUri(__DIR__ . '/data/reference/definitions.yaml')));

        $this->assertTrue(isset($openapi->components->schemas['Pet']));
        $this->assertEquals(['One', 'Two'], $openapi->components->schemas['Pet']->properties['typeA']->enum);
        $this->assertEquals(['One', 'Two'], $openapi->components->schemas['Pet']->properties['typeB']->enum);
        $this->assertEquals(['Three', 'Two'], $openapi->components->schemas['Pet']->properties['typeC']->enum);
        $this->assertEquals(['Four', 'Five'], $openapi->components->schemas['Pet']->properties['typeD']->enum);
        $this->assertEquals(['Five', 'Six'], $openapi->components->schemas['Pet']->properties['typeE']->enum);
    }

    /**
     * References to references fail as "cyclic reference"
     *
     * @see https://github.com/cebe/php-openapi/issues/57
     */
    public function testTransitiveReference(): void
    {
        $schema = <<<'YAML'
openapi: 3.0.2
info:
  title: 'City API'
  version: dev
paths:
  '/city':
    get:
      description: 'Get City'
      responses:
        '200':
          description: 'success'
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/City'
components:
  schemas:
    City:
      $ref: '#/components/schemas/Named'
    Named:
      type: string

YAML;

        $openapi = Reader::readFromYaml($schema);
        $openapi->resolveReferences(new ReferenceContext($openapi, 'file:///tmp/openapi.yaml'));

        $this->assertTrue(isset($openapi->components->schemas['City']));
        $this->assertTrue(isset($openapi->components->schemas['Named']));
        $this->assertEquals('string', $openapi->components->schemas['Named']->type);
        $this->assertEquals('string', $openapi->components->schemas['City']->type);
        $this->assertEquals('string', $openapi->paths['/city']->get->responses['200']->content['application/json']->schema->type);
    }

    /**
     * References to references fail as "cyclic reference"
     *
     * @see https://github.com/cebe/php-openapi/issues/54
     */
    public function testTransitiveReferenceToFile(): void
    {
        $schema = <<<'YAML'
openapi: 3.0.2
info:
  title: 'Dog API'
  version: dev
paths:
  '/dog':
    get:
      description: 'Get Dog'
      responses:
        '200':
          description: 'success'
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Dog'
components:
  schemas:
    Dog:
      $ref: 'definitions.yaml#/Dog'

YAML;

        $openapi = Reader::readFromYaml($schema);
        $openapi->resolveReferences(new ReferenceContext($openapi, $this->createFileUri(__DIR__ . '/data/reference/definitions.yaml')));

        $this->assertTrue(isset($openapi->components->schemas['Dog']));
        $this->assertEquals('object', $openapi->components->schemas['Dog']->type);
        $this->assertEquals('object', $openapi->components->schemas['Dog']->type);
        $this->assertEquals('object', $openapi->paths['/dog']->get->responses['200']->content['application/json']->schema->type);
    }

    public function testTransitiveReferenceCyclic(): void
    {
        $schema = <<<'YAML'
openapi: 3.0.2
info:
  title: 'City API'
  version: dev
paths:
  '/city':
    get:
      description: 'Get City'
      responses:
        '200':
          description: 'success'
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/City'
components:
  schemas:
    City:
      $ref: '#/components/schemas/City'

YAML;

        $openapi = Reader::readFromYaml($schema);

        $this->expectException(UnresolvableReferenceException::class);
        $this->expectExceptionMessage('Cyclic reference detected on a Reference Object.');

        $openapi->resolveReferences(new ReferenceContext($openapi, 'file:///tmp/openapi.yaml'));
    }

    public function testTransitiveReferenceOverTwoFiles(): void
    {
        $openapi = Reader::readFromYamlFile(__DIR__ . '/data/reference/structure.yaml', OpenApi::class, ReferenceContext::RESOLVE_MODE_INLINE);

        $yaml = Writer::writeToYaml($openapi);

        $expected = <<<'YAML'
openapi: 3.0.0
info:
  title: 'Ref Example'
  version: 1.0.0
paths:
  /pet:
    get:
      responses:
        '200':
          description: 'return a pet'
  /cat:
    get:
      responses:
        '200':
          description: 'return a cat'

YAML;
        // remove line endings to make string equal on windows
        $expected = preg_replace('~\R~', "\n", $expected);
        $this->assertEquals($expected, $yaml, $yaml);
    }

    public function testReferencedCommonParamsInReferencedPath(): void
    {
        $openapi  = Reader::readFromYamlFile(__DIR__ . '/data/reference/ReferencedCommonParamsInReferencedPath.yml', OpenApi::class, ReferenceContext::RESOLVE_MODE_INLINE);
        $yaml     = Writer::writeToYaml($openapi);
        $expected = <<<'YAML'
openapi: 3.0.0
info:
  title: 'Nested reference with common path params'
  version: 1.0.0
paths:
  /example:
    get:
      responses:
        '200':
          description: 'OK if common params can be references'
      request:
        content:
          application/json:
            examples:
              user:
                summary: 'User Example'
                externalValue: ./paths/examples/user-example.json
              userex:
                summary: 'External User Example'
                externalValue: 'https://api.example.com/examples/user-example.json'
    parameters:
      -
        name: test
        in: header
        description: 'Test parameter to be referenced'
        required: true
        schema:
          enum:
            - test
          type: string
    x-something: something
  /something:
    get:
      responses:
        '200':
          description: 'OK if common params can be references'
    parameters:
      -
        name: test
        in: header
        description: 'Test parameter to be referenced'
        required: true
        schema:
          enum:
            - test
          type: string
    x-something: something

YAML;
        // remove line endings to make string equal on windows
        $expected = preg_replace('~\R~', "\n", $expected);
        $this->assertEquals($expected, $yaml, $yaml);
    }

    public function testResolveRelativePathInline(): void
    {
        $openapi = Reader::readFromYamlFile(__DIR__ . '/data/reference/openapi_models.yaml', OpenApi::class, ReferenceContext::RESOLVE_MODE_INLINE);

        $yaml = Writer::writeToYaml($openapi);

        $expected = <<<'YAML'
openapi: 3.0.3
info:
  title: 'Link Example'
  version: 1.0.0
paths:
  /pet:
    get:
      responses:
        '200':
          description: 'return a pet'
components:
  schemas:
    Pet:
      type: object
      properties:
        id:
          type: integer
          format: int64
        cat:
          $ref: '#/components/schemas/Cat'
      description: 'A Pet'
    Cat:
      type: object
      properties:
        id:
          type: integer
          format: int64
        name:
          type: string
          description: 'the cats name'
        pet:
          $ref: '#/components/schemas/Pet'
      description: 'A Cat'

YAML;
        // remove line endings to make string equal on windows
        $expected = preg_replace('~\R~', "\n", $expected);
        $this->assertEquals($expected, $yaml, $yaml);
    }

    public function testResolveRelativePathAll(): void
    {
        $openapi = Reader::readFromYamlFile(__DIR__ . '/data/reference/openapi_models.yaml', OpenApi::class, ReferenceContext::RESOLVE_MODE_ALL);

        $yaml = Writer::writeToYaml($openapi);

        $expected = <<<'YAML'
        openapi: 3.0.3
        info:
          title: 'Link Example'
          version: 1.0.0
        paths:
          /pet:
            get:
              responses:
                '200':
                  description: 'return a pet'
        components:
          schemas:
            Pet:
              type: object
              properties:
                id:
                  type: integer
                  format: int64
                cat:
                  type: object
                  properties:
                    id:
                      type: integer
                      format: int64
                    name:
                      type: string
                      description: 'the cats name'
                    pet:
                      $ref: '#/components/schemas/Pet'
                  description: 'A Cat'
              description: 'A Pet'
            Cat:
              type: object
              properties:
                id:
                  type: integer
                  format: int64
                name:
                  type: string
                  description: 'the cats name'
                pet:
                  type: object
                  properties:
                    id:
                      type: integer
                      format: int64
                    cat:
                      $ref: '#/components/schemas/Cat'
                  description: 'A Pet'
              description: 'A Cat'
        
        YAML;
        // remove line endings to make string equal on windows
        $expected = preg_replace('~\R~', "\n", $expected);
        $this->assertEquals($expected, $yaml, $yaml);
    }

    public function testValidate(): void
    {
        $reference = new Reference(['$ref' => '#/components/schemas/Dummy'], OpenApiVersion::VERSION_3_0, null);
        self::assertTrue($reference->validate());

        $reference = new Reference([
            '$ref' => '#/components/schemas/Dummy',
            'description' => 'Description',
        ], OpenApiVersion::VERSION_3_0, null);
        self::assertFalse($reference->validate());

        $reference = new Reference([
            '$ref' => '#/components/schemas/Dummy',
            'description' => 'Description',
            'summary' => 'Summary',
        ], OpenApiVersion::VERSION_3_1, null);
        self::assertTrue($reference->validate());

        $reference = new Reference([
            '$ref' => '#/components/schemas/Dummy',
            'invalid' => 'invalid',
        ], OpenApiVersion::VERSION_3_1, null);
        self::assertFalse($reference->validate());
        self::assertStringContainsString('only summary and description are allowed', $reference->getErrors()[0]);
    }

    public function testFieldsAreOverwriteReference(): void
    {
        $openapi = Reader::readFromYamlFile(
            __DIR__ . '/data/reference/ReferenceOpenApi31.yml',
            OpenApi::class,
            ReferenceContext::RESOLVE_MODE_ALL,
        );

        $yaml = Writer::writeToYaml($openapi);

        $expected = <<<'YAML'
        openapi: 3.1.0
        info:
          title: 'Reference allows description and summary'
          version: 1.0.0
        paths:
          /example1:
            get:
              description: 'Default Description'
              summary: 'Default Summary'
              responses:
                '200':
                  description: OK
          /example2:
            get:
              description: 'New Description'
              summary: 'New Summary'
              responses:
                '200':
                  description: OK
          /example3:
            get:
              requestBody:
                name: Foo
                in: path
                description: 'Default Description'
                required: true
                schema: null
            post:
              requestBody:
                name: Foo
                in: path
                description: Overwrite
                required: true
                schema: null
        components:
          parameters:
            FooInput:
              name: Foo
              in: path
              description: 'Default Description'
              required: true
              schema: null
          schemas:
            ReferenceCommon:
              description: 'Default Description'
              summary: 'Default Summary'
              responses:
                '200':
                  description: OK
        
        YAML;
        // remove line endings to make string equal on windows
        $expected = preg_replace('~\R~', "\n", $expected);
        $this->assertEquals($expected, $yaml, $yaml);
    }
}
