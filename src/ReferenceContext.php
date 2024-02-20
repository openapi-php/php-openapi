<?php

declare(strict_types=1);

namespace openapiphp\openapi;

use openapiphp\openapi\exceptions\IOException;
use openapiphp\openapi\exceptions\UnresolvableReferenceException;
use openapiphp\openapi\json\JsonPointer;
use openapiphp\openapi\spec\Reference;
use Symfony\Component\Yaml\Yaml;

use function count;
use function explode;
use function file_get_contents;
use function implode;
use function json_decode;
use function ltrim;
use function mb_strrpos;
use function mb_substr;
use function parse_url;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function stripos;
use function strtr;
use function substr;

use const PHP_OS;

/**
 * ReferenceContext represents a context in which references are resolved.
 */
class ReferenceContext
{
    /**
     * only resolve external references.
     * The result will be a single API description file with references
     * inside of the file structure.
     */
    public const RESOLVE_MODE_INLINE = 'inline';

    /**
     * resolve all references, except recursive ones.
     */
    public const RESOLVE_MODE_ALL = 'all';

    /**
     * @var bool whether to throw UnresolvableReferenceException in case a reference can not
     * be resolved. If `false` errors are added to the Reference Objects error list instead.
     */
    public bool $throwException = true;
    public string $mode         = self::RESOLVE_MODE_ALL;
    private readonly string $_uri;
    private readonly ReferenceContextCache $_cache;

    /**
     * @param SpecObjectInterface   $_baseSpec the base object of the spec.
     * @param string                $uri       the URI to the base object.
     * @param ReferenceContextCache $cache     cache instance for storing referenced file data.
     *
     * @throws UnresolvableReferenceException in case an invalid or non-absolute URI is provided.
     */
    public function __construct(
        private readonly SpecObjectInterface|null $_baseSpec,
        string $uri,
        ReferenceContextCache|null $cache = null,
    ) {
        $this->_uri   = $this->normalizeUri($uri);
        $this->_cache = $cache ?? new ReferenceContextCache();
        if ($cache instanceof ReferenceContextCache || ! $this->_baseSpec instanceof SpecObjectInterface) {
            return;
        }

        $this->_cache->set($this->_uri, null, $this->_baseSpec);
    }

    public function getCache(): ReferenceContextCache
    {
        return $this->_cache;
    }

    /** @throws UnresolvableReferenceException in case an invalid or non-absolute URI is provided. */
    private function normalizeUri(string $uri): string
    {
        if (str_contains($uri, '://')) {
            $parts = parse_url($uri);
            if (isset($parts['path'])) {
                $parts['path'] = $this->reduceDots($parts['path']);
            }

            return $this->buildUri($parts);
        }

        if (str_starts_with($uri, '/')) {
            $uri = $this->reduceDots($uri);

            return 'file://' . $uri;
        }

        if (stripos(PHP_OS, 'WIN') === 0 && str_starts_with(substr($uri, 1), ':\\')) {
            $uri = $this->reduceDots($uri);

            return 'file://' . strtr($uri, [' ' => '%20', '\\' => '/']);
        }

        throw new UnresolvableReferenceException('Can not resolve references for a specification given as a relative path.');
    }

    /** @param array<string, string> $parts */
    private function buildUri(array $parts): string
    {
        $scheme   = empty($parts['scheme']) ? '' : $parts['scheme'] . '://';
        $host     = $parts['host'] ?? '';
        $port     = empty($parts['port']) ? '' : ':' . $parts['port'];
        $user     = $parts['user'] ?? '';
        $pass     = empty($parts['pass']) ? ''  : ':' . $parts['pass'];
        $pass     = $user || $pass ? $pass . '@' : '';
        $path     = $parts['path'] ?? '';
        $query    = empty($parts['query']) ? '' : '?' . $parts['query'];
        $fragment = empty($parts['fragment']) ? '' : '#' . $parts['fragment'];

        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }

    private function reduceDots(string $path): string
    {
        $parts        = explode('/', ltrim($path, '/'));
        $c            = count($parts);
        $parentOffset = 1;
        for ($i = 0; $i < $c; $i++) {
            if ($parts[$i] === '.') {
                unset($parts[$i]);
                continue;
            }

            if ($i <= 0 || $parts[$i] !== '..' || $parts[$i - $parentOffset] === '..') {
                continue;
            }

            unset($parts[$i - $parentOffset]);
            unset($parts[$i]);
            $parentOffset += 2;
        }

        return '/' . implode('/', $parts);
    }

    /**
     * Returns parent directory's path.
     * This method is similar to `dirname()` except that it will treat
     * both \ and / as directory separators, independent of the operating system.
     *
     * @see http://www.php.net/manual/en/function.dirname.php
     * @see https://github.com/yiisoft/yii2/blob/e1f6761dfd9eba1ff1260cd37b04936aaa4959b5/framework/helpers/BaseStringHelper.php#L75-L92
     *
     * @param string $path A path string.
     *
     * @return string the parent directory's path.
     */
    private function dirname(string $path): string
    {
        $pos = mb_strrpos(str_replace('\\', '/', $path), '/');
        if ($pos !== false) {
            return mb_substr($path, 0, $pos);
        }

        return '';
    }

    public function getBaseSpec(): SpecObjectInterface|null
    {
        return $this->_baseSpec;
    }

    public function getUri(): string
    {
        return $this->_uri;
    }

    /**
     * Resolve a relative URI to an absolute URI in the current context.
     *
     * @throws UnresolvableReferenceException
     */
    public function resolveRelativeUri(string $uri): string
    {
        $parts = parse_url($uri);
        // absolute URI, no need to combine with baseURI
        if (isset($parts['scheme'])) {
            if (isset($parts['path'])) {
                $parts['path'] = $this->reduceDots($parts['path']);
            }

            return $this->buildUri($parts);
        }

        // convert absolute path on windows to a file:// URI. This is probably incomplete but should work with the majority of paths.
        if (stripos(PHP_OS, 'WIN') === 0 && str_starts_with(substr($uri, 1), ':\\')) {
            // convert absolute path on windows to a file:// URI. This is probably incomplete but should work with the majority of paths.
            $absoluteUri = 'file:///' . strtr($uri, [' ' => '%20', '\\' => '/']);

            return $absoluteUri
                . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
        }

        $baseUri   = $this->getUri();
        $baseParts = parse_url($baseUri);
        if (isset($parts['path'][0]) && $parts['path'][0] === '/') {
            // absolute path
            $baseParts['path'] = $this->reduceDots($parts['path']);
        } elseif (isset($parts['path'])) {
            // relative path
            $baseParts['path'] = $this->reduceDots(rtrim($this->dirname($baseParts['path'] ?? ''), '/') . '/' . $parts['path']);
        } else {
            throw new UnresolvableReferenceException(sprintf('Invalid URI: \'%s\'', $uri));
        }

        $baseParts['query']    = $parts['query'] ?? null;
        $baseParts['fragment'] = $parts['fragment'] ?? null;

        return $this->buildUri($baseParts);
    }

    /**
     * Fetch referenced file by URI.
     *
     * The current context will cache files by URI, so they are only loaded once.
     *
     * @throws IOException in case the file is not readable or fetching the file
     * from a remote URL failed.
     */
    public function fetchReferencedFile(string $uri): mixed
    {
        if ($this->_cache->has('FILE_CONTENT://' . $uri, 'FILE_CONTENT')) {
            return $this->_cache->get('FILE_CONTENT://' . $uri, 'FILE_CONTENT');
        }

        $content = file_get_contents($uri);
        if ($content === false) {
            $e           = new IOException(sprintf('Failed to read file: \'%s\'', $uri));
            $e->fileName = $uri;

            throw $e;
        }

        // TODO lazy content detection, should be improved
        if (str_starts_with(ltrim($content), '{')) {
            $parsedContent = json_decode($content, true);
        } else {
            $parsedContent = Yaml::parse($content);
        }

        $this->_cache->set('FILE_CONTENT://' . $uri, 'FILE_CONTENT', $parsedContent);

        return $parsedContent;
    }

    /**
     * Retrieve the referenced data via JSON pointer.
     *
     * This function caches referenced data to make sure references to the same
     * data structures end up being the same object instance in PHP.
     *
     * @return SpecObjectInterface|array<string, mixed>|null
     */
    public function resolveReferenceData(
        string $uri,
        JsonPointer $pointer,
        mixed $data,
        ReferenceTarget|null $toType,
        OpenApiVersion|null $openApiVersion,
    ): SpecObjectInterface|array|string|null {
        $ref = $uri . '#' . $pointer->getPointer();
        if ($this->_cache->has($ref, $toType?->asString())) {
            return $this->_cache->get($ref, $toType?->asString());
        }

        $referencedData = $pointer->evaluate($data);

        if ($referencedData === null) {
            return null;
        }

        // transitive reference
        if (isset($referencedData['$ref'])) {
            return new Reference($referencedData, $openApiVersion, $toType);
        }

        $referencedObject = $toType?->createInstance($referencedData);
        $referencedData   = $referencedObject ?? $referencedData;

        $this->_cache->set($ref, $toType?->asString(), $referencedData);

        return $referencedData;
    }
}
