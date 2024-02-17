<?php

/**
 * @copyright Copyright (c) 2018 Carsten Brandt <mail@cebe.cc> and contributors
 * @license https://github.com/cebe/php-openapi/blob/master/LICENSE
 */

use cebe\openapi\Reader;
use cebe\openapi\spec\Header;

#[\PHPUnit\Framework\Attributes\CoversClass(\cebe\openapi\spec\Header::class)]
class HeaderTest extends \PHPUnit\Framework\TestCase
{
    public function testRead(): void
    {
        /** @var $header Header */
        $header = Reader::readFromJson(<<<JSON
{
  "description": "The number of allowed requests in the current period",
  "schema": {
    "type": "integer"
  }
}
JSON
            , Header::class);

        $result = $header->validate();
        $this->assertEquals([], $header->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('The number of allowed requests in the current period', $header->description);
        $this->assertInstanceOf(\cebe\openapi\spec\Schema::class, $header->schema);
        $this->assertEquals('integer', $header->schema->type);
    }

}
