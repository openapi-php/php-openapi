<?php

declare(strict_types=1);

namespace openapiphp\openapi\json;

use ArrayAccess;
use Stringable;

use function array_key_exists;
use function array_map;
use function array_pop;
use function explode;
use function implode;
use function is_array;
use function is_object;
use function preg_match;
use function property_exists;
use function sprintf;
use function strtr;
use function substr;

/**
 * Represents a JSON Pointer (RFC 6901)
 *
 * A JSON Pointer only works in the context of a single JSON document,
 * if you need to reference values in external documents, use [[JsonReference]] instead.
 *
 * @link https://tools.ietf.org/html/rfc6901
 * @see JsonReference
 */
final class JsonPointer implements Stringable
{
    private readonly string $_pointer;

    /**
     * JSON Pointer constructor.
     *
     * @param string $pointer The JSON Pointer.
     * Must be either an empty string (for referencing the whole document), or a string starting with `/`.
     *
     * @throws InvalidJsonPointerSyntaxException in case an invalid JSON pointer string is passed.
     */
    public function __construct(string $pointer)
    {
        if (! preg_match('~^(/[^/]*)*$~', $pointer)) {
            throw new InvalidJsonPointerSyntaxException('Invalid JSON Pointer syntax: ' . $pointer);
        }

        $this->_pointer = $pointer;
    }

    public function __toString(): string
    {
        return $this->_pointer;
    }

    /** @return string returns the JSON Pointer. */
    public function getPointer(): string
    {
        return $this->_pointer;
    }

    /** @return list<string> the JSON pointer path as array. */
    public function getPath(): array
    {
        if ($this->_pointer === '') {
            return [];
        }

        $pointer = substr($this->_pointer, 1);

        return array_map([self::class, 'decode'], explode('/', $pointer));
    }

    /**
     * Append a new part to the JSON path.
     *
     * @param string $subpath the path element to append.
     *
     * @return JsonPointer a new JSON pointer pointing to the subpath.
     */
    public function append(string $subpath): JsonPointer
    {
        return new JsonPointer($this->_pointer . '/' . self::encode($subpath));
    }

    /**
     * Returns a JSON pointer to the parent path element of this pointer.
     *
     * @return JsonPointer|null a new JSON pointer pointing to the parent element
     * or null if this pointer already points to the document root.
     */
    public function parent(): JsonPointer|null
    {
        $path = $this->getPath();
        if ($path === []) {
            return null;
        }

        array_pop($path);
        if ($path === []) {
            return new JsonPointer('');
        }

        return new JsonPointer('/' . implode('/', array_map([self::class, 'encode'], $path)));
    }

    /**
     * Evaluate the JSON Pointer on the provided document.
     *
     * Note that this does only resolve the JSON Pointer, it will not load external
     * documents by URI. Loading the Document from the URI is supposed to be done outside of this class.
     *
     * @throws NonexistentJsonPointerReferenceException
     */
    public function evaluate(mixed $jsonDocument): mixed
    {
        $currentReference = $jsonDocument;
        $currentPath      = '';

        foreach ($this->getPath() as $part) {
            if (is_array($currentReference)) {
                //                if (!preg_match('~^([1-9]*[0-9]|-)$~', $part)) {
                //                    throw new NonexistentJsonPointerReferenceException(
                //                        "Failed to evaluate pointer '$this->_pointer'. Invalid pointer path '$part' for Array at path '$currentPath'."
                //                    );
                //                }
                if ($part === '-' || ! array_key_exists($part, $currentReference)) {
                    throw new NonexistentJsonPointerReferenceException(
                        sprintf('Failed to evaluate pointer \'%s\'. Array has no member %s at path \'%s\'.', $this->_pointer, $part, $currentPath),
                    );
                }

                $currentReference = $currentReference[$part];
            } elseif ($currentReference instanceof ArrayAccess) {
                if (! $currentReference->offsetExists($part)) {
                    throw new NonexistentJsonPointerReferenceException(
                        sprintf('Failed to evaluate pointer \'%s\'. Array has no member %s at path \'%s\'.', $this->_pointer, $part, $currentPath),
                    );
                }

                $currentReference = $currentReference[$part];
            } elseif (is_object($currentReference)) {
                if (! isset($currentReference->$part) && ! property_exists($currentReference, $part)) {
                    throw new NonexistentJsonPointerReferenceException(
                        sprintf('Failed to evaluate pointer \'%s\'. Object has no member %s at path \'%s\'.', $this->_pointer, $part, $currentPath),
                    );
                }

                $currentReference = $currentReference->$part;
            } else {
                throw new NonexistentJsonPointerReferenceException(
                    sprintf('Failed to evaluate pointer \'%s\'. Value at path \'%s\' is neither an array nor an object.', $this->_pointer, $currentPath),
                );
            }

            $currentPath = sprintf('%s/%s', $currentPath, $part);
        }

        return $currentReference;
    }

    /**
     * Encodes a string for use inside of a JSON pointer.
     */
    public static function encode(string $string): string
    {
        return strtr($string, [
            '~' => '~0',
            '/' => '~1',
        ]);
    }

    /**
     * Decodes a string used inside of a JSON pointer.
     */
    public static function decode(string $string): string
    {
        return strtr($string, [
            '~1' => '/',
            '~0' => '~',
        ]);
    }
}
