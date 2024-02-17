<?php

declare(strict_types=1);

namespace OpenApiTest;

use openapiphp\openapi\spec\OpenApi;
use openapiphp\openapi\spec\PathItem;
use openapiphp\openapi\spec\SecurityRequirement;
use openapiphp\openapi\Writer;
use PHPUnit\Framework\TestCase;

use function array_merge;
use function preg_replace;

class WriterTest extends TestCase
{
    /** @param array<string, mixed> $merge */
    private function createOpenAPI(array $merge = []): OpenApi
    {
        return new OpenApi(array_merge([
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [],
        ], $merge));
    }

    public function testWriteJson(): void
    {
        $openapi = $this->createOpenAPI();

        $json = Writer::writeToJson($openapi);

        $this->assertEquals(
            preg_replace(
                '~\R~',
                "\n",
                <<<'JSON'
                {
                    "openapi": "3.0.0",
                    "info": {
                        "title": "Test API",
                        "version": "1.0.0"
                    },
                    "paths": {}
                }
                JSON,
            ),
            $json,
        );
    }

    public function testWriteJsonMofify(): void
    {
        $openapi = $this->createOpenAPI();

        $openapi->paths['/test'] = new PathItem(['description' => 'something']);

        $json = Writer::writeToJson($openapi);

        $this->assertEquals(
            preg_replace(
                '~\R~',
                "\n",
                <<<'JSON'
{
    "openapi": "3.0.0",
    "info": {
        "title": "Test API",
        "version": "1.0.0"
    },
    "paths": {
        "\/test": {
            "description": "something"
        }
    }
}
JSON,
            ),
            $json,
        );
    }

    public function testWriteYaml(): void
    {
        $openapi = $this->createOpenAPI();

        $yaml = Writer::writeToYaml($openapi);

        $this->assertEquals(
            preg_replace(
                '~\R~',
                "\n",
                <<<'YAML'
openapi: 3.0.0
info:
  title: 'Test API'
  version: 1.0.0
paths: {  }

YAML,
            ),
            $yaml,
        );
    }

    public function testWriteEmptySecurityJson(): void
    {
        $openapi = $this->createOpenAPI([
            'security' => [],
        ]);

        $json = Writer::writeToJson($openapi);

        $this->assertEquals(
            preg_replace(
                '~\R~',
                "\n",
                <<<'JSON'
{
    "openapi": "3.0.0",
    "info": {
        "title": "Test API",
        "version": "1.0.0"
    },
    "paths": {},
    "security": []
}
JSON,
            ),
            $json,
        );
    }

    public function testWriteEmptySecurityYaml(): void
    {
        $openapi = $this->createOpenAPI([
            'security' => [],
        ]);

        $yaml = Writer::writeToYaml($openapi);

        $this->assertEquals(
            preg_replace(
                '~\R~',
                "\n",
                <<<'YAML'
openapi: 3.0.0
info:
  title: 'Test API'
  version: 1.0.0
paths: {  }
security: []

YAML,
            ),
            $yaml,
        );
    }

    public function testWriteEmptySecurityPartJson(): void
    {
        $openapi = $this->createOpenAPI([
            'security' => [new SecurityRequirement(['Bearer' => []])],
        ]);

        $json = Writer::writeToJson($openapi);

        $this->assertEquals(
            preg_replace(
                '~\R~',
                "\n",
                <<<'JSON'
{
    "openapi": "3.0.0",
    "info": {
        "title": "Test API",
        "version": "1.0.0"
    },
    "paths": {},
    "security": [
        {
            "Bearer": []
        }
    ]
}
JSON,
            ),
            $json,
        );
    }

    public function testWriteEmptySecurityPartYaml(): void
    {
        $openapi = $this->createOpenAPI([
            'security' => [new SecurityRequirement(['Bearer' => []])],
        ]);

        $yaml = Writer::writeToYaml($openapi);

        $this->assertEquals(
            preg_replace(
                '~\R~',
                "\n",
                <<<'YAML'
openapi: 3.0.0
info:
  title: 'Test API'
  version: 1.0.0
paths: {  }
security:
  -
    Bearer: []

YAML,
            ),
            $yaml,
        );
    }
}
