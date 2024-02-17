<?php

declare(strict_types=1);

namespace openapiphp\openapi;

use function array_key_exists;

/**
 * ReferenceContextCache represents a cache storage for caching content of referenced files.
 */
class ReferenceContextCache
{
    /** @var array<string, mixed> */
    private array $_cache = [];

    public function set(string $ref, string|null $type, mixed $data): void
    {
        $this->_cache[$ref][$type ?? ''] = $data;

        // store fallback value for resolving with unknown type
        if ($type === null || isset($this->_cache[$ref][''])) {
            return;
        }

        $this->_cache[$ref][''] = $data;
    }

    public function get(string $ref, string|null $type): mixed
    {
        return $this->_cache[$ref][$type ?? ''] ?? null;
    }

    public function has(string $ref, string|null $type): bool
    {
        return isset($this->_cache[$ref]) &&
            array_key_exists($type ?? '', $this->_cache[$ref]);
    }
}
