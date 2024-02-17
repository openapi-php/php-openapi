<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\Header;
use openapiphp\openapi\spec\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;

#[CoversClass(Header::class)]
class HeaderTest extends TestCase
{
    public function testRead(): void
    {
        $header = Reader::readFromJson(<<<'JSON'
{
  "description": "The number of allowed requests in the current period",
  "schema": {
    "type": "integer"
  }
}
JSON
            , Header::class);
        assert($header instanceof Header);

        $result = $header->validate();
        $this->assertEquals([], $header->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('The number of allowed requests in the current period', $header->description);
        $this->assertInstanceOf(Schema::class, $header->schema);
        $this->assertEquals('integer', $header->schema->type);
    }
}
