<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\Xml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;

#[CoversClass(Xml::class)]
class XmlTest extends TestCase
{
    public function testRead(): void
    {
        $xml = Reader::readFromYaml(<<<'YAML'
name: animal
attribute: true
namespace: http://example.com/schema/sample
prefix: sample
wrapped: false
YAML
            , Xml::class);
        assert($xml instanceof Xml);

        $result = $xml->validate();
        $this->assertEquals([], $xml->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('animal', $xml->name);
        $this->assertTrue($xml->attribute);
        $this->assertEquals('http://example.com/schema/sample', $xml->namespace);
        $this->assertEquals('sample', $xml->prefix);
        $this->assertFalse($xml->wrapped);

        $xml = Reader::readFromYaml(<<<'YAML'
name: animal
YAML
            , Xml::class);
        assert($xml instanceof Xml);

        $result = $xml->validate();
        $this->assertEquals([], $xml->getErrors());
        $this->assertTrue($result);

        // attribute Default value is false.
        $this->assertFalse($xml->attribute);
        // wrapped Default value is false.
        $this->assertFalse($xml->wrapped);
    }
}
