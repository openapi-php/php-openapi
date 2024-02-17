<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\json\JsonPointer;
use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\Components;
use openapiphp\openapi\spec\ExternalDocumentation;
use openapiphp\openapi\spec\Info;
use openapiphp\openapi\spec\License;
use openapiphp\openapi\spec\OpenApi;
use openapiphp\openapi\spec\PathItem;
use openapiphp\openapi\spec\Paths;
use openapiphp\openapi\spec\SecurityRequirement;
use openapiphp\openapi\spec\Server;
use openapiphp\openapi\spec\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;

use function array_merge;
use function assert;
use function file_get_contents;
use function json_decode;
use function print_r;
use function sprintf;
use function str_contains;
use function strtolower;
use function substr;

#[CoversClass(OpenApi::class)]
class OpenApiTest extends TestCase
{
    public function testEmpty(): void
    {
        $openapi = new OpenApi([]);

        $this->assertFalse($openapi->validate());
        $this->assertEquals([
            'OpenApi is missing required property: openapi',
            'OpenApi is missing required property: info',
            'OpenApi is missing at least one of the following required properties: paths, webhooks, components',
        ], $openapi->getErrors());

        // check default value of servers
        // https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#openapiObject
        // If the servers property is not provided, or is an empty array, the default value would be a Server Object with a url value of /.
        $this->assertCount(1, $openapi->servers);
        $this->assertEquals('/', $openapi->servers[0]->url);
    }

    public function testReadPetStore(): void
    {
        $openApiFile = __DIR__ . '/../../vendor/oai/openapi-specification-3.0/examples/v3.0/petstore.yaml';

        $yaml    = Yaml::parse(file_get_contents($openApiFile));
        $openapi = new OpenApi($yaml);

        $result = $openapi->validate();
        $this->assertEquals([], $openapi->getErrors());
        $this->assertTrue($result);

        // openapi
        $this->assertEquals('3.0.0', $openapi->openapi);

        // info
        $this->assertInstanceOf(Info::class, $openapi->info);
        $this->assertEquals('1.0.0', $openapi->info->version);
        $this->assertEquals('Swagger Petstore', $openapi->info->title);
        // info.license
        $this->assertInstanceOf(License::class, $openapi->info->license);
        $this->assertEquals('MIT', $openapi->info->license->name);
        // info.contact
        $this->assertNull($openapi->info->contact);

        // servers
        $this->assertIsArray($openapi->servers);

        $this->assertCount(1, $openapi->servers);
        foreach ($openapi->servers as $server) {
            $this->assertInstanceOf(Server::class, $server);
            $this->assertEquals('http://petstore.swagger.io/v1', $server->url);
        }

        // paths
        $this->assertInstanceOf(Paths::class, $openapi->paths);

        // components
        $this->assertInstanceOf(Components::class, $openapi->components);

        // security
        $this->assertAllInstanceOf(SecurityRequirement::class, $openapi->security);

        // tags
        $this->assertAllInstanceOf(Tag::class, $openapi->tags);

        // externalDocs
        $this->assertNull($openapi->externalDocs);
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

    /** @return iterable<string> */
    public static function specProvider(): iterable
    {
        // examples from https://github.com/OAI/OpenAPI-Specification/tree/master/examples/v3.0
        $oaiExamples = [
            // TODO symfony/yaml can not read this file!?
//            __DIR__ . '/../../vendor/oai/openapi-specification-3.0/examples/v3.0/api-with-examples.yaml',
            __DIR__ . '/../../vendor/oai/openapi-specification-3.0/examples/v3.0/callback-example.yaml',
            __DIR__ . '/../../vendor/oai/openapi-specification-3.0/examples/v3.0/link-example.yaml',
            __DIR__ . '/../../vendor/oai/openapi-specification-3.0/examples/v3.0/petstore.yaml',
            __DIR__ . '/../../vendor/oai/openapi-specification-3.0/examples/v3.0/petstore-expanded.yaml',
            __DIR__ . '/../../vendor/oai/openapi-specification-3.0/examples/v3.0/uspto.yaml',
        ];

        // examples from https://github.com/Mermade/openapi3-examples
        $mermadeExamples = [
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/externalPathItemRef.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/deprecated.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/swagger2openapi/openapi.json',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example1_from_._Different_parameters.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example1_from_._Fixed_file.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example1_from_._Fixed_multipart.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example1_from_._Improved_examples.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example1_from_._Improved_pathdescriptions.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example1_from_._Improved_securityschemes.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example1_from_._Improved_serverseverywhere.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example1_from_._New_callbacks.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example1_from_._New_links.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example2_from_._Different_parameters.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example2_from_._Different_requestbody.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example2_from_._Different_servers.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example2_from_._Fixed_multipart.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example2_from_._Improved_securityschemes.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example2_from_._New_callbacks.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example2_from_._New_links.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example3_from_._Different_parameters.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example3_from_._Different_servers.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example4_from_._Different_parameters.md.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/gluecon/example5_from_._Different_parameters.md.yaml',
            // TODO symfony/yaml can not read this file!?
//            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/OAI/api-with-examples.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/OAI/petstore-expanded.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/OAI/petstore.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/pass/OAI/uber.yaml',

            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/malicious/rapid7-html.json',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/malicious/rapid7-java.json',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/malicious/rapid7-js.json',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/malicious/rapid7-php.json',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/malicious/rapid7-ruby.json',
//            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.0/malicious/yamlbomb.yaml',

            // OpenAPI 3.1 examples
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.1/pass/minimal_comp.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.1/pass/minimal_hooks.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.1/pass/minimal_paths.yaml',
            __DIR__ . '/../../vendor/mermade/openapi3-examples/3.1/pass/path_var_empty_pathitem.yaml',
        ];

        // examples from https://github.com/APIs-guru/openapi-directory/tree/openapi3.0.0/APIs
        $apisGuruExamples = [];
        $it               = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../vendor/apis-guru/openapi-directory/APIs'));
        assert($it instanceof RecursiveDirectoryIterator || $it instanceof RecursiveIteratorIterator);
        $it->rewind();
        while ($it->valid()) {
            if ($it->getBasename() === 'openapi.yaml') {
                $apisGuruExamples[] = $it->key();
            }

            $it->next();
        }

        $nexmoExamples = [];
        $it            = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../resources/definitions'));
        assert($it instanceof RecursiveDirectoryIterator || $it instanceof RecursiveIteratorIterator);
        $it->rewind();
        while ($it->valid()) {
            if (
                $it->getExtension() === 'yml'
                && ! str_contains((string) $it->getSubPath(), 'common')
                && $it->getBasename() !== 'voice.v2.yml' // contains invalid references
            ) {
                $nexmoExamples[] = $it->key();
            }

            $it->next();
        }

        $all = array_merge(
            $oaiExamples,
            $mermadeExamples,
            $apisGuruExamples,
            $nexmoExamples,
        );
        foreach ($all as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('specProvider')]
    public function testSpecs(string $openApiFile): void
    {
        if (strtolower(substr($openApiFile, -5, 5)) === '.json') {
            $json    = json_decode(file_get_contents($openApiFile), true);
            $openapi = new OpenApi($json);
        } else {
            $yaml    = Yaml::parse(file_get_contents($openApiFile));
            $openapi = new OpenApi($yaml);
        }

        $openapi->setDocumentContext($openapi, new JsonPointer(''));

        $result = $openapi->validate();
        $this->assertEquals([], $openapi->getErrors(), print_r($openapi->getErrors(), true));
        $this->assertTrue($result);

        // openapi
        $this->assertNotSame(OpenApi::VERSION_UNSUPPORTED, $openapi->getMajorVersion());

        // info
        $this->assertInstanceOf(Info::class, $openapi->info);

        // servers
        $this->assertAllInstanceOf(Server::class, $openapi->servers);

        // paths
        if ($openapi->paths !== null) {
            $this->assertInstanceOf(Paths::class, $openapi->paths);
        }

        // webhooks
        if ($openapi->webhooks !== null) {
            $this->assertAllInstanceOf(PathItem::class, $openapi->webhooks);
        }

        // components
        if ($openapi->components !== null) {
            $this->assertInstanceOf(Components::class, $openapi->components);
        }

        // security
        $this->assertAllInstanceOf(SecurityRequirement::class, $openapi->security);

        // tags
        $this->assertAllInstanceOf(Tag::class, $openapi->tags);

        // externalDocs
        if ($openapi->externalDocs === null) {
            return;
        }

        $this->assertInstanceOf(ExternalDocumentation::class, $openapi->externalDocs);
    }

    public function testVersions(): void
    {
        $yaml    = <<<'YAML'
openapi: 3.0.2
info:
  title: Test API
  version: 1
paths: []
YAML;
        $openapi = Reader::readFromYaml($yaml);
        $this->assertTrue($openapi->validate(), print_r($openapi->getErrors(), true));
        $this->assertEquals('3.0', $openapi->getMajorVersion());

        $yaml    = <<<'YAML'
openapi: 3.1.0
info:
  title: Test API
  version: 1
paths: []
YAML;
        $openapi = Reader::readFromYaml($yaml);
        $this->assertTrue($openapi->validate(), print_r($openapi->getErrors(), true));
        $this->assertEquals('3.1', $openapi->getMajorVersion());
    }
}
