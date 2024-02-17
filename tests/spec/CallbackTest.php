<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\Callback;
use openapiphp\openapi\spec\PathItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;

#[CoversClass(Callback::class)]
class CallbackTest extends TestCase
{
    public function testRead(): void
    {
        $callback = Reader::readFromYaml(<<<'YAML'
'http://notificationServer.com?transactionId={$request.body#/id}&email={$request.body#/email}':
  post:
    requestBody:
      description: Callback payload
      content: 
        'application/json':
          schema:
            $ref: '#/components/schemas/SomePayload'
    responses:
      '200':
        description: webhook successfully processed and no retries will be performed
YAML
            , Callback::class);
        assert($callback instanceof Callback);

        $result = $callback->validate();
        $this->assertEquals([], $callback->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('http://notificationServer.com?transactionId={$request.body#/id}&email={$request.body#/email}', $callback->getUrl());
        $this->assertInstanceOf(PathItem::class, $callback->getRequest());
    }
}
