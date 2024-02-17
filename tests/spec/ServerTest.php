<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\Server;
use openapiphp\openapi\spec\ServerVariable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;

#[CoversClass(Server::class)]
#[CoversClass(ServerVariable::class)]
class ServerTest extends TestCase
{
    public function testRead(): void
    {
        $server = Reader::readFromJson(<<<'JSON'
{
  "url": "https://{username}.gigantic-server.com:{port}/{basePath}",
  "description": "The production API server",
  "variables": {
    "username": {
      "default": "demo",
      "description": "this value is assigned by the service provider, in this example `gigantic-server.com`"
    },
    "port": {
      "enum": [
        "8443",
        "443"
      ],
      "default": "8443"
    },
    "basePath": {
      "default": "v2"
    }
  }
}
JSON
            , Server::class);
        assert($server instanceof Server);

        $result = $server->validate();
        $this->assertEquals([], $server->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('https://{username}.gigantic-server.com:{port}/{basePath}', $server->url);
        $this->assertEquals('The production API server', $server->description);
        $this->assertCount(3, $server->variables);
        $this->assertEquals('demo', $server->variables['username']->default);
        $this->assertEquals('this value is assigned by the service provider, in this example `gigantic-server.com`', $server->variables['username']->description);
        $this->assertEquals('8443', $server->variables['port']->default);

        $server = Reader::readFromJson(<<<'JSON'
{
  "description": "The production API server"
}
JSON
            , Server::class);
        assert($server instanceof Server);

        $result = $server->validate();
        $this->assertEquals(['Server is missing required property: url'], $server->getErrors());
        $this->assertFalse($result);

        $server = Reader::readFromJson(<<<'JSON'
{
  "url": "https://{username}.gigantic-server.com:{port}/{basePath}",
  "description": "The production API server",
  "variables": {
    "username": {
      "description": "this value is assigned by the service provider, in this example `gigantic-server.com`"
    }
  }
}
JSON
            , Server::class);
        assert($server instanceof Server);

        $result = $server->validate();
        $this->assertEquals(['ServerVariable is missing required property: default'], $server->getErrors());
        $this->assertFalse($result);
    }
}
