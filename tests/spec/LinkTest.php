<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\Link;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;

#[CoversClass(Link::class)]
class LinkTest extends TestCase
{
    public function testRead(): void
    {
        $link = Reader::readFromJson(<<<'JSON'
{
    "operationId": "getUserAddress",
    "parameters": {
        "userId": "test.path.id"
    }
}
JSON
            , Link::class);
        assert($link instanceof Link);

        $result = $link->validate();
        $this->assertEquals([], $link->getErrors());
        $this->assertTrue($result);

        $this->assertEquals(null, $link->operationRef);
        $this->assertEquals('getUserAddress', $link->operationId);
        $this->assertEquals(['userId' => 'test.path.id'], $link->parameters);
        $this->assertEquals(null, $link->requestBody);
        $this->assertEquals(null, $link->server);
    }

    public function testValidateBothOperationIdAndOperationRef(): void
    {
        $link = Reader::readFromJson(<<<'JSON'
{
    "operationId": "getUserAddress",
    "operationRef": "getUserAddressRef"
}
JSON
            , Link::class);
        assert($link instanceof Link);

        $result = $link->validate();
        $this->assertEquals(['Link: operationId and operationRef are mutually exclusive.'], $link->getErrors());
        $this->assertFalse($result);
    }
}
