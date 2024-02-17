<?php

declare(strict_types=1);

namespace OpenApiTest\json;

use openapiphp\openapi\json\JsonPointer;
use openapiphp\openapi\json\JsonReference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_decode;

class JsonPointerTest extends TestCase
{
    /** @return iterable<list<string>> */
    public static function encodeDecodeData(): iterable
    {
        return [
            ['~0', '~'],
            ['~1', '/'],
            ['something', 'something'],
            ['~01', '~1'],
            ['~1~0', '/~'],
            ['~0~1', '~/'],
            ['~0~0', '~~'],
            ['~1~1', '//'],
            ['some~1path~1', 'some/path/'],
            ['1some0~11path0~1', '1some0/1path0/'],
            ['1some0~11path~00', '1some0/1path~0'],
        ];
    }

    #[DataProvider('encodeDecodeData')]
    public function testEncode(string $encoded, string $decoded): void
    {
        $this->assertEquals($encoded, JsonPointer::encode($decoded));
    }

    #[DataProvider('encodeDecodeData')]
    public function testDecode(string $encoded, string $decoded): void
    {
        $this->assertEquals($decoded, JsonPointer::decode($encoded));
    }

    /** @link https://tools.ietf.org/html/rfc6901#section-5 */
    public static function rfcJsonDocument(): string
    {
        return <<<'JSON'
{
      "foo": ["bar", "baz"],
      "": 0,
      "a/b": 1,
      "c%d": 2,
      "e^f": 3,
      "g|h": 4,
      "i\\j": 5,
      "k\"l": 6,
      " ": 7,
      "m~n": 8
}
JSON;
    }

    /**
     * @link https://tools.ietf.org/html/rfc6901#section-5
     *
     * @return iterable<list<mixed>>
     */
    public static function rfcExamples(): iterable
    {
        $return = [
            [''      , '#'      , json_decode(self::rfcJsonDocument())],
            ['/foo'  , '#/foo'  , ['bar', 'baz']],
            ['/foo/0', '#/foo/0', 'bar'],
            ['/'     , '#/'     , 0],
            ['/a~1b' , '#/a~1b' , 1],
            ['/c%d'  , '#/c%25d', 2],
            ['/e^f'  , '#/e%5Ef', 3],
            ['/g|h'  , '#/g%7Ch', 4],
            ['/i\\j' , '#/i%5Cj', 5],
            ['/k"l' , '#/k%22l', 6],
            ['/ '    , '#/%20'  , 7],
            ['/m~0n' , '#/m~0n' , 8],
        ];
        foreach ($return as $example) {
            $example[3] = self::rfcJsonDocument();

            yield $example;
        }
    }

    /** @return iterable<list<mixed>> */
    public static function allExamples(): iterable
    {
        yield from self::rfcExamples();

        yield ['/a#b', '#/a%23b', 16, '{"a#b": 16}'];
    }

    #[DataProvider('allExamples')]
    public function testUriEncoding(string $jsonPointer, string $uriJsonPointer, mixed $expectedEvaluation): void
    {
        $pointer = new JsonPointer($jsonPointer);
        $this->assertSame($jsonPointer, $pointer->getPointer());
        $this->assertSame($uriJsonPointer, JsonReference::createFromUri('', $pointer)->getReference());

        $reference = JsonReference::createFromReference($uriJsonPointer);
        $this->assertSame($jsonPointer, $reference->getJsonPointer()->getPointer());
        $this->assertSame('', $reference->getDocumentUri());
        $this->assertSame($uriJsonPointer, $reference->getReference());

        $reference = JsonReference::createFromReference('somefile.json' . $uriJsonPointer);
        $this->assertSame($jsonPointer, $reference->getJsonPointer()->getPointer());
        $this->assertSame('somefile.json', $reference->getDocumentUri());
        $this->assertSame('somefile.json' . $uriJsonPointer, $reference->getReference());
    }

    #[DataProvider('rfcExamples')]
    public function testEvaluation(string $jsonPointer, string $uriJsonPointer, mixed $expectedEvaluation): void
    {
        $document = json_decode($this->rfcJsonDocument());
        $pointer  = new JsonPointer($jsonPointer);
        $this->assertEquals($expectedEvaluation, $pointer->evaluate($document));

        $document  = json_decode($this->rfcJsonDocument());
        $reference = JsonReference::createFromReference($uriJsonPointer);
        $this->assertEquals($expectedEvaluation, $reference->getJsonPointer()->evaluate($document));
    }

    public function testEvaluationCases(): void
    {
        $document = (object) [
            '' => (object) ['' => 42],
        ];
        $pointer  = new JsonPointer('//');
        $this->assertSame(42, $pointer->evaluate($document));

        $document = ['1' => null];
        $pointer  = new JsonPointer('/1');
        $this->assertNull($pointer->evaluate($document));

        $document = (object) ['k' => null];
        $pointer  = new JsonPointer('/k');
        $this->assertNull($pointer->evaluate($document));
    }

    public function testParent(): void
    {
        $this->assertNull((new JsonPointer(''))->parent());
        $this->assertSame('', (new JsonPointer('/'))->parent()->getPointer());
        $this->assertSame('/', (new JsonPointer('//'))->parent()->getPointer());
        $this->assertSame('', (new JsonPointer('/some'))->parent()->getPointer());
        $this->assertSame('/some', (new JsonPointer('/some/path'))->parent()->getPointer());
        $this->assertSame('', (new JsonPointer('/a~1b'))->parent()->getPointer());
        $this->assertSame('/a~1b', (new JsonPointer('/a~1b/path'))->parent()->getPointer());
        $this->assertSame('/some', (new JsonPointer('/some/a~1b'))->parent()->getPointer());
    }

    public function testAppend(): void
    {
        $this->assertSame('/some', (new JsonPointer(''))->append('some')->getPointer());
        $this->assertSame('/~1some', (new JsonPointer(''))->append('/some')->getPointer());
        $this->assertSame('/~0some', (new JsonPointer(''))->append('~some')->getPointer());
        $this->assertSame('/path/some', (new JsonPointer('/path'))->append('some')->getPointer());
        $this->assertSame('/path/~1some', (new JsonPointer('/path'))->append('/some')->getPointer());
        $this->assertSame('/path/~0some', (new JsonPointer('/path'))->append('~some')->getPointer());
        $this->assertSame('/a~1b/some', (new JsonPointer('/a~1b'))->append('some')->getPointer());
        $this->assertSame('/a~1b/~1some', (new JsonPointer('/a~1b'))->append('/some')->getPointer());
        $this->assertSame('/a~1b/~0some', (new JsonPointer('/a~1b'))->append('~some')->getPointer());
    }
}
