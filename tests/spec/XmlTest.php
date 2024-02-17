<?php

/**
 * @copyright Copyright (c) 2018 Carsten Brandt <mail@cebe.cc> and contributors
 * @license https://github.com/cebe/php-openapi/blob/master/LICENSE
 */

use cebe\openapi\Reader;
use cebe\openapi\spec\Xml;

#[\PHPUnit\Framework\Attributes\CoversClass(\cebe\openapi\spec\Xml::class)]
class XmlTest extends \PHPUnit\Framework\TestCase
{
    public function testRead(): void
    {
        /** @var $xml Xml */
        $xml = Reader::readFromYaml(<<<YAML
name: animal
attribute: true
namespace: http://example.com/schema/sample
prefix: sample
wrapped: false
YAML
            , Xml::class);

        $result = $xml->validate();
        $this->assertEquals([], $xml->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('animal', $xml->name);
        $this->assertTrue($xml->attribute);
        $this->assertEquals('http://example.com/schema/sample', $xml->namespace);
        $this->assertEquals('sample', $xml->prefix);
        $this->assertFalse($xml->wrapped);

        /** @var $xml Xml */
        $xml = Reader::readFromYaml(<<<YAML
name: animal
YAML
            , Xml::class);

        $result = $xml->validate();
        $this->assertEquals([], $xml->getErrors());
        $this->assertTrue($result);

        // attribute Default value is false.
        $this->assertFalse($xml->attribute);
        // wrapped Default value is false.
        $this->assertFalse($xml->wrapped);
    }
}
