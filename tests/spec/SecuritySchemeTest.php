<?php

/**
 * @copyright Copyright (c) 2018 Carsten Brandt <mail@cebe.cc> and contributors
 * @license https://github.com/cebe/php-openapi/blob/master/LICENSE
 */

use cebe\openapi\Reader;
use cebe\openapi\spec\OAuthFlow;
use cebe\openapi\spec\OAuthFlows;
use cebe\openapi\spec\SecurityRequirement;
use cebe\openapi\spec\SecurityScheme;

#[\PHPUnit\Framework\Attributes\CoversClass(\cebe\openapi\spec\SecurityScheme::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\cebe\openapi\spec\OAuthFlows::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\cebe\openapi\spec\OAuthFlow::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\cebe\openapi\spec\SecurityRequirement::class)]
class SecuritySchemeTest extends \PHPUnit\Framework\TestCase
{
    public function testRead(): void
    {
        /** @var $securityScheme SecurityScheme */
        $securityScheme = Reader::readFromYaml(<<<YAML
type: http
scheme: basic
YAML
            , SecurityScheme::class);

        $result = $securityScheme->validate();
        $this->assertEquals([], $securityScheme->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('http', $securityScheme->type);
        $this->assertEquals('basic', $securityScheme->scheme);

        /** @var $securityScheme SecurityScheme */
        $securityScheme = Reader::readFromYaml(<<<YAML
scheme: basic
YAML
            , SecurityScheme::class);

        $result = $securityScheme->validate();
        $this->assertEquals(['SecurityScheme is missing required property: type'], $securityScheme->getErrors());
        $this->assertFalse($result);

        /** @var $securityScheme SecurityScheme */
        $securityScheme = Reader::readFromYaml(<<<YAML
type: apiKey
YAML
            , SecurityScheme::class);

        $result = $securityScheme->validate();
        $this->assertEquals([
            'SecurityScheme is missing required property: name',
            'SecurityScheme is missing required property: in',
        ], $securityScheme->getErrors());
        $this->assertFalse($result);

        /** @var $securityScheme SecurityScheme */
        $securityScheme = Reader::readFromYaml(<<<YAML
type: http
YAML
            , SecurityScheme::class);

        $result = $securityScheme->validate();
        $this->assertEquals([
            'SecurityScheme is missing required property: scheme',
        ], $securityScheme->getErrors());
        $this->assertFalse($result);

        /** @var $securityScheme SecurityScheme */
        $securityScheme = Reader::readFromYaml(<<<YAML
type: oauth2
YAML
            , SecurityScheme::class);

        $result = $securityScheme->validate();
        $this->assertEquals([
            'SecurityScheme is missing required property: flows',
        ], $securityScheme->getErrors());
        $this->assertFalse($result);

        /** @var $securityScheme SecurityScheme */
        $securityScheme = Reader::readFromYaml(<<<YAML
type: openIdConnect
YAML
            , SecurityScheme::class);

        $result = $securityScheme->validate();
        $this->assertEquals([
            'SecurityScheme is missing required property: openIdConnectUrl',
        ], $securityScheme->getErrors());
        $this->assertFalse($result);
    }

    public function testOAuth2(): void
    {
        /** @var $securityScheme SecurityScheme */
        $securityScheme = Reader::readFromYaml(<<<YAML
type: oauth2
flows:
  implicit:
    authorizationUrl: https://example.com/api/oauth/dialog
YAML
            , SecurityScheme::class);

        $result = $securityScheme->validate();
        $this->assertEquals([
            'OAuthFlow is missing required property: scopes',
        ], $securityScheme->getErrors());
        $this->assertFalse($result);

        /** @var $securityScheme SecurityScheme */
        $securityScheme = Reader::readFromYaml(<<<YAML
type: oauth2
flows:
  implicit:
    authorizationUrl: https://example.com/api/oauth/dialog
    scopes:
      write:pets: modify pets in your account
      read:pets: read your pets
  authorizationCode:
    authorizationUrl: https://example.com/api/oauth/dialog
    tokenUrl: https://example.com/api/oauth/token
    scopes:
      write:pets: modify pets in your account
      read:pets: read your pets 
YAML
            , SecurityScheme::class);

        $result = $securityScheme->validate();
        $this->assertEquals([], $securityScheme->getErrors());
        $this->assertTrue($result);

        $this->assertInstanceOf(OAuthFlows::class, $securityScheme->flows);
        $this->assertInstanceOf(OAuthFlow::class, $securityScheme->flows->implicit);
        $this->assertInstanceOf(OAuthFlow::class, $securityScheme->flows->authorizationCode);
        $this->assertNull($securityScheme->flows->clientCredentials);
        $this->assertNull($securityScheme->flows->password);

        $this->assertEquals('https://example.com/api/oauth/dialog', $securityScheme->flows->implicit->authorizationUrl);
        $this->assertEquals([
            'write:pets' => 'modify pets in your account',
            'read:pets' =>  'read your pets',
        ], $securityScheme->flows->implicit->scopes);
    }

    public function testSecurityRequirement(): void
    {
        /** @var $securityRequirement SecurityRequirement */
        $securityRequirement = Reader::readFromYaml(<<<YAML
api_key: []
YAML
            , SecurityRequirement::class);

        $result = $securityRequirement->validate();
        $this->assertEquals([], $securityRequirement->getErrors());
        $this->assertTrue($result);

        $this->assertSame([], $securityRequirement->api_key);

        /** @var $securityRequirement SecurityRequirement */
        $securityRequirement = Reader::readFromYaml(<<<YAML
petstore_auth:
- write:pets
- read:pets
YAML
            , SecurityRequirement::class);

        $result = $securityRequirement->validate();
        $this->assertEquals([], $securityRequirement->getErrors());
        $this->assertTrue($result);

        $this->assertSame(['write:pets', 'read:pets'], $securityRequirement->petstore_auth);
    }

    public function testDefaultSecurity(): void
    {
        $openapi = Reader::readFromYaml(
            <<<YAML
paths:
  /path/one:
    post:
      description: path one
      # [...]
      security: [] # default security

  /path/two:
    post:
      description: path two
      # [...]
      # No security entry defined there

components:
  securitySchemes:
    Bearer:
      type: http
      scheme: bearer
      bearerFormat: JWT

security:
  - Bearer: []
YAML
        );

        $this->assertSame([], $openapi->paths->getPath('/path/one')->post->security);
        $this->assertSame(null, $openapi->paths->getPath('/path/two')->post->security);

        $this->assertCount(1, $openapi->security);
        $this->assertSame([], $openapi->security[0]->Bearer);
    }
}
