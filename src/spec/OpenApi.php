<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\SpecBaseObject;

use function preg_match;

/**
 * This is the root document object of the OpenAPI document.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#openapi-object
 *
 * @property string $openapi
 * @property Info $info
 * @property array<Server> $servers
 * @property Paths|array<PathItem> $paths
 * @property Components|null $components
 * @property array<PathItem>|null $webhooks
 * @property array<SecurityRequirement> $security
 * @property array<Tag> $tags
 * @property ExternalDocumentation|null $externalDocs
 */
final class OpenApi extends SpecBaseObject
{
    public const VERSION_3_0         = '3.0';
    public const VERSION_3_1         = '3.1';
    public const VERSION_UNSUPPORTED = 'unsupported';

    /**
     * Pattern used to validate OpenAPI versions.
     */
    public const PATTERN_VERSION = '/^(3\.(0|1))\.\d+(-rc\d)?$/i';

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    public function performValidation(): void
    {
        if ($this->getMajorVersion() === self::VERSION_3_0) {
            $this->requireProperties(['openapi', 'info', 'paths']);
        } else {
            $this->requireProperties(['openapi', 'info'], ['paths', 'webhooks', 'components']);
        }

        if (empty($this->openapi) || preg_match(self::PATTERN_VERSION, $this->openapi)) {
            return;
        }

        $this->addError('Unsupported openapi version: ' . $this->openapi);
    }

    /**
     * Returns the OpenAPI major version of the loaded OpenAPI description.
     *
     * @return string This returns a value of one of the `VERSION_*`-constants. Currently supported versions are:
     *
     * - `VERSION_3_0 = '3.0'`
     * - `VERSION_3_1 = '3.1'`
     *
     * For unsupported version, this function will return `VERSION_UNSUPPORTED = 'unsupported'`
     */
    public function getMajorVersion(): string
    {
        if (empty($this->openapi)) {
            return self::VERSION_UNSUPPORTED;
        }

        if (preg_match(self::PATTERN_VERSION, $this->openapi, $matches)) {
            switch ($matches[1]) {
                case '3.0':
                    return self::VERSION_3_0;

                case '3.1':
                    return self::VERSION_3_1;
            }
        }

        return self::VERSION_UNSUPPORTED;
    }

    /** @inheritDoc */
    protected function attributes(): array
    {
        return [
            'openapi' => Type::STRING,
            'info' => Info::class,
            'servers' => [Server::class],
            'paths' => Paths::class,
            'components' => Components::class,
            'externalDocs' => ExternalDocumentation::class,
            'security' => [SecurityRequirement::class],
            'tags' => [Tag::class],
            'webhooks' => [PathItem::class],
        ];
    }

    /** @inheritDoc */
    protected function attributeDefaults(): array
    {
        return [
            // Spec: If the servers property is not provided, or is an empty array,
            // the default value would be a Server Object with a url value of /.
            'servers' => [new Server(['url' => '/'])],
        ];
    }

    public function __get(string $name): mixed
    {
        $ret = parent::__get($name);
        // Spec: If the servers property is not provided, or is an empty array,
        // the default value would be a Server Object with a url value of /.
        if ($name === 'servers' && $ret === []) {
            return $this->attributeDefaults()['servers'];
        }

        return $ret;
    }
}
