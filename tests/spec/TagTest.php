<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\ExternalDocumentation;
use openapiphp\openapi\spec\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;

#[CoversClass(Tag::class)]
class TagTest extends TestCase
{
    public function testRead(): void
    {
        $tag = Reader::readFromYaml(<<<'YAML'
name: pet
description: Pets operations
YAML
            , Tag::class);
        assert($tag instanceof Tag);

        $result = $tag->validate();
        $this->assertEquals([], $tag->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('pet', $tag->name);
        $this->assertEquals('Pets operations', $tag->description);
        $this->assertNull($tag->externalDocs);

        $tag = Reader::readFromYaml(<<<'YAML'
description: Pets operations
externalDocs:
  url: https://example.com
YAML
            , Tag::class);
        assert($tag instanceof Tag);

        $result = $tag->validate();
        $this->assertEquals(['Tag is missing required property: name'], $tag->getErrors());
        $this->assertFalse($result);

        $this->assertInstanceOf(ExternalDocumentation::class, $tag->externalDocs);
    }
}
