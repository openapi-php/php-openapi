<?php

declare(strict_types=1);

namespace openapiphp\openapi\spec;

use openapiphp\openapi\OpenApiVersion;
use openapiphp\openapi\SpecBaseObject;

use function is_string;
use function preg_match;

/**
 * This is the root document object of the OpenAPI document.
 *
 * @link https://github.com/OAI/OpenAPI-Specification/blob/3.0.2/versions/3.0.2.md#openapi-object
 *
 * @property string $openapi
 * @property Info $info
 * @property array<Server> $servers
 * @property Paths $paths
 * @property Components|null $components
 * @property array<PathItem>|null $webhooks
 * @property array<SecurityRequirement> $security
 * @property array<Tag> $tags
 * @property ExternalDocumentation|null $externalDocs
 */
final class OpenApi extends SpecBaseObject
{
    /**
     * Pattern used to validate OpenAPI versions.
     */
    public const PATTERN_VERSION = '/^(3\.(0|1))\.\d+(-rc\d)?$/i';

    /** @inheritDoc */
    public function __construct(array $data, OpenApiVersion|null $defaultOpenApiVersion = OpenApiVersion::VERSION_UNSUPPORTED)
    {
        $matches = [];
        if (isset($data['openapi']) && is_string($data['openapi'])) {
            preg_match(self::PATTERN_VERSION, $data['openapi'], $matches);
        }

        $openApiVersion = match ($matches[1] ?? '') {
            OpenApiVersion::VERSION_3_0->value => OpenApiVersion::VERSION_3_0,
            OpenApiVersion::VERSION_3_1->value => OpenApiVersion::VERSION_3_1,
            default => $defaultOpenApiVersion
        };

        parent::__construct($data, $openApiVersion);
    }

    /**
     * Perform validation on this object, check data against OpenAPI Specification rules.
     */
    public function performValidation(): void
    {
        if ($this->openApiVersion === OpenApiVersion::VERSION_3_0) {
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
     * For unsupported version, this function will return `VERSION_UNSUPPORTED = 'unsupported'`
     */
    public function getMajorVersion(): OpenApiVersion
    {
        return $this->openApiVersion ?? OpenApiVersion::VERSION_UNSUPPORTED;
    }

    /** @inheritDoc */
    public function attributes(): array
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
            'servers' => [new Server(['url' => '/'], $this->openApiVersion)],
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
