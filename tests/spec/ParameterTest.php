<?php

declare(strict_types=1);

namespace OpenApiTest\spec;

use openapiphp\openapi\Reader;
use openapiphp\openapi\spec\MediaType;
use openapiphp\openapi\spec\Parameter;
use openapiphp\openapi\spec\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;
use function sprintf;

#[CoversClass(Parameter::class)]
class ParameterTest extends TestCase
{
    public function testRead(): void
    {
        $parameter = Reader::readFromYaml(<<<'YAML'
name: token
in: header
description: token to be passed as a header
required: true
schema:
  type: array
  items:
    type: integer
    format: int64
style: simple
YAML
            , Parameter::class);
        assert($parameter instanceof Parameter);

        $result = $parameter->validate();
        $this->assertEquals([], $parameter->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('token', $parameter->name);
        $this->assertEquals('header', $parameter->in);
        $this->assertEquals('token to be passed as a header', $parameter->description);
        $this->assertTrue($parameter->required);

        $this->assertInstanceOf(Schema::class, $parameter->schema);
        $this->assertEquals('array', $parameter->schema->type);

        $this->assertEquals('simple', $parameter->style);

        $parameter = Reader::readFromYaml(<<<'YAML'
in: query
name: coordinates
content:
  application/json:
    schema:
      type: object
      required:
        - lat
        - long
      properties:
        lat:
          type: number
        long:
          type: number
YAML
            , Parameter::class);
        assert($parameter instanceof Parameter);

        $result = $parameter->validate();
        $this->assertEquals([], $parameter->getErrors());
        $this->assertTrue($result);

        $this->assertEquals('coordinates', $parameter->name);
        $this->assertEquals('query', $parameter->in);
        // required default value is false.
        $this->assertFalse($parameter->required);
        // deprecated default value is false.
        $this->assertFalse($parameter->deprecated);
        // allowEmptyValue default value is false.
        $this->assertFalse($parameter->allowEmptyValue);

        $this->assertInstanceOf(MediaType::class, $parameter->content['application/json']);
        $this->assertInstanceOf(Schema::class, $parameter->content['application/json']->schema);
    }

    public function testDefaultValuesQuery(): void
    {
        $parameter = Reader::readFromYaml(<<<'YAML'
name: token
in: query
YAML
            , Parameter::class);
        assert($parameter instanceof Parameter);

        $result = $parameter->validate();
        $this->assertEquals([], $parameter->getErrors());
        $this->assertTrue($result);

        // default value for style parameter in query param
        $this->assertEquals('form', $parameter->style);
        $this->assertTrue($parameter->explode);
        $this->assertFalse($parameter->allowReserved);
    }

    public function testDefaultValuesPath(): void
    {
        $parameter = Reader::readFromYaml(<<<'YAML'
name: token
in: path
required: true
YAML
            , Parameter::class);
        assert($parameter instanceof Parameter);

        $result = $parameter->validate();
        $this->assertEquals([], $parameter->getErrors());
        $this->assertTrue($result);

        // default value for style parameter in query param
        $this->assertEquals('simple', $parameter->style);
        $this->assertFalse($parameter->explode);
    }

    public function testDefaultValuesHeader(): void
    {
        $parameter = Reader::readFromYaml(<<<'YAML'
name: token
in: header
YAML
            , Parameter::class);
        assert($parameter instanceof Parameter);

        $result = $parameter->validate();
        $this->assertEquals([], $parameter->getErrors());
        $this->assertTrue($result);

        // default value for style parameter in query param
        $this->assertEquals('simple', $parameter->style);
        $this->assertFalse($parameter->explode);
    }

    public function testDefaultValuesCookie(): void
    {
        $parameter = Reader::readFromYaml(<<<'YAML'
name: token
in: cookie
YAML
            , Parameter::class);
        assert($parameter instanceof Parameter);

        $result = $parameter->validate();
        $this->assertEquals([], $parameter->getErrors());
        $this->assertTrue($result);

        // default value for style parameter in query param
        $this->assertEquals('form', $parameter->style);
        $this->assertTrue($parameter->explode);
    }

    public function testItValidatesSchemaAndContentCombination(): void
    {
        $parameter = Reader::readFromYaml(<<<'YAML'
name: token
in: cookie
schema:
  type: object
content:
  application/json:
    schema:
      type: object
YAML
            , Parameter::class);
        assert($parameter instanceof Parameter);

        $result = $parameter->validate();
        $this->assertEquals(['A Parameter Object MUST contain either a schema property, or a content property, but not both.'], $parameter->getErrors());
        $this->assertFalse($result);
    }

    public function testItValidatesContentCanHaveOnlySingleKey(): void
    {
        $parameter = Reader::readFromYaml(<<<'YAML'
name: token
in: cookie
content:
  application/json:
    schema:
      type: object
  application/xml:
    schema:
      type: object
YAML
            , Parameter::class);
        assert($parameter instanceof Parameter);

        $result = $parameter->validate();
        $this->assertEquals(['A Parameter Object with Content property MUST have A SINGLE content type.'], $parameter->getErrors());
        $this->assertFalse($result);
    }

    public function testItValidatesSupportedSerializationStyles(): void
    {
        // 1. Prepare test inputs
        $specTemplate     = <<<'YAML'
name: token
required: true
in: %s
style: %s
YAML;
        $goodCombinations = [
            'path' => ['simple', 'label', 'matrix'],
            'query' => ['form', 'spaceDelimited', 'pipeDelimited', 'deepObject'],
            'header' => ['simple'],
            'cookie' => ['form'],
        ];
        $badCombinations  = [
            'path' => ['unknown', 'form', 'spaceDelimited', 'pipeDelimited', 'deepObject'],
            'query' => ['unknown', 'simple', 'label', 'matrix'],
            'header' => ['unknown', 'form', 'spaceDelimited', 'pipeDelimited', 'deepObject', 'matrix'],
            'cookie' => ['unknown', 'spaceDelimited', 'pipeDelimited', 'deepObject', 'matrix', 'label', 'matrix'],
        ];

        // 2. Run tests for valid input
        foreach ($goodCombinations as $in => $styles) {
            foreach ($styles as $style) {
                $parameter = Reader::readFromYaml(sprintf($specTemplate, $in, $style), Parameter::class);
                assert($parameter instanceof Parameter);
                $result = $parameter->validate();
                $this->assertEquals([], $parameter->getErrors());
                $this->assertTrue($result);
            }
        }

        // 2. Run tests for invalid input
        foreach ($badCombinations as $in => $styles) {
            foreach ($styles as $style) {
                $parameter = Reader::readFromYaml(sprintf($specTemplate, $in, $style), Parameter::class);
                assert($parameter instanceof Parameter);
                $result = $parameter->validate();
                $this->assertEquals(['A Parameter Object DOES NOT support this serialization style.'], $parameter->getErrors());
                $this->assertFalse($result);
            }
        }
    }
}
