<?php

declare(strict_types=1);

namespace openapiphp\openapi;

use openapiphp\openapi\exceptions\IOException;
use openapiphp\openapi\spec\OpenApi;
use Symfony\Component\Yaml\Yaml;

use function file_put_contents;
use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

/**
 * Utility class to simplify writing JSON or YAML OpenAPI specs.
 */
final class Writer
{
    /**
     * Convert OpenAPI spec object to JSON data.
     *
     * @param SpecObjectInterface|OpenApi $object the OpenApi object instance.
     * @param int                         $flags  json_encode() flags. Parameter available since version 1.7.0.
     *
     * @return string JSON string.
     */
    public static function writeToJson(
        SpecObjectInterface $object,
        int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
    ): string {
        return json_encode($object->getSerializableData(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Convert OpenAPI spec object to YAML data.
     *
     * @param SpecObjectInterface|OpenApi $object the OpenApi object instance.
     *
     * @return string YAML string.
     */
    public static function writeToYaml(SpecObjectInterface $object): string
    {
        return Yaml::dump(
            $object->getSerializableData(),
            256,
            2,
            Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE,
        );
    }

    /**
     * Write OpenAPI spec object to JSON file.
     *
     * @param SpecObjectInterface|OpenApi $object   the OpenApi object instance.
     * @param string                      $fileName file name to write to.
     *
     * @throws IOException when writing the file fails.
     */
    public static function writeToJsonFile(SpecObjectInterface $object, string $fileName): void
    {
        if (file_put_contents($fileName, self::writeToJson($object)) === false) {
            throw new IOException(sprintf('Failed to write file: \'%s\'', $fileName));
        }
    }

    /**
     * Write OpenAPI spec object to YAML file.
     *
     * @param SpecObjectInterface|OpenApi $object   the OpenApi object instance.
     * @param string                      $fileName file name to write to.
     *
     * @throws IOException when writing the file fails.
     */
    public static function writeToYamlFile(SpecObjectInterface $object, string $fileName): void
    {
        if (file_put_contents($fileName, self::writeToYaml($object)) === false) {
            throw new IOException(sprintf('Failed to write file: \'%s\'', $fileName));
        }
    }
}
