<?php

declare(strict_types=1);

namespace openapiphp\openapi;

use openapiphp\openapi\exceptions\IOException;
use openapiphp\openapi\exceptions\TypeErrorException;
use openapiphp\openapi\exceptions\UnresolvableReferenceException;
use openapiphp\openapi\json\InvalidJsonPointerSyntaxException;
use openapiphp\openapi\json\JsonPointer;
use openapiphp\openapi\spec\OpenApi;
use Symfony\Component\Yaml\Yaml;

use function file_get_contents;
use function is_string;
use function json_decode;
use function sprintf;

/**
 * Utility class to simplify reading JSON or YAML OpenAPI specs.
 */
final class Reader
{
    /**
     * Populate OpenAPI spec object from JSON data.
     *
     * @param string $json     the JSON string to decode.
     * @param string $baseType the base Type to instantiate. This must be an instance of [[SpecObjectInterface]].
     * The default is [[OpenApi]] which is the base type of a OpenAPI specification file.
     * You may choose a different type if you instantiate objects from sub sections of a specification.
     * @phpstan-param class-string<T> $baseType
     *
     * @return SpecObjectInterface|OpenApi the OpenApi object instance.
     * The type of the returned object depends on the `$baseType` argument.
     * @phpstan-return T
     *
     * @throws TypeErrorException in case invalid spec data is supplied.
     *
     * @phpstan-template T of SpecObjectInterface
     */
    public static function readFromJson(string $json, string $baseType = OpenApi::class): SpecObjectInterface
    {
        return new $baseType(json_decode($json, true));
    }

    /**
     * Populate OpenAPI spec object from YAML data.
     *
     * @param string                            $yaml     the YAML string to decode.
     * @param class-string<SpecObjectInterface> $baseType the base Type to instantiate. This must be an instance of [[SpecObjectInterface]].
     * The default is [[OpenApi]] which is the base type of a OpenAPI specification file.
     * You may choose a different type if you instantiate objects from sub sections of a specification.
     * @phpstan-param class-string<T> $baseType
     *
     * @return SpecObjectInterface|OpenApi the OpenApi object instance.
     * The type of the returned object depends on the `$baseType` argument.
     * @phpstan-return T
     *
     * @throws TypeErrorException in case invalid spec data is supplied.
     *
     * @phpstan-template T of SpecObjectInterface
     */
    public static function readFromYaml(string $yaml, string $baseType = OpenApi::class): SpecObjectInterface
    {
        return new $baseType(Yaml::parse($yaml));
    }

    /**
     * Populate OpenAPI spec object from a JSON file.
     *
     * @param string      $fileName          the file name of the file to be read.
     *               If `$resolveReferences` is true (the default), this should be an absolute URL, a `file://` URI or
     *               an absolute path to allow resolving relative path references.
     * @param string      $baseType          the base Type to instantiate. This must be an instance of [[SpecObjectInterface]].
     *               The default is [[OpenApi]] which is the base type of a OpenAPI specification file.
     *               You may choose a different type if you instantiate objects from sub sections of a specification.
     * @param bool|string $resolveReferences whether to automatically resolve references in the specification.
     * If `true`, all [[Reference]] objects will be replaced with their referenced spec objects by calling
     * [[SpecObjectInterface::resolveReferences()]].
     * Since version 1.5.0 this can be a string indicating the reference resolving mode:
     * - `inline` only resolve references to external files.
     * - `all` resolve all references except recursive references.
     * @phpstan-param class-string<T> $baseType
     *
     * @return SpecObjectInterface|OpenApi the OpenApi object instance.
     * The type of the returned object depends on the `$baseType` argument.
     * @phpstan-return T
     *
     * @throws TypeErrorException in case invalid spec data is supplied.
     * @throws UnresolvableReferenceException in case references could not be resolved.
     * @throws IOException when the file is not readable.
     * @throws InvalidJsonPointerSyntaxException in case an invalid JSON pointer string is passed to the spec references.
     *
     * @phpstan-template T of SpecObjectInterface
     */
    public static function readFromJsonFile(
        string $fileName,
        string $baseType = OpenApi::class,
        bool|string $resolveReferences = true,
    ): SpecObjectInterface {
        $fileContent = file_get_contents($fileName);
        if ($fileContent === false) {
            $e           = new IOException(sprintf("Failed to read file: '%s'", $fileName));
            $e->fileName = $fileName;

            throw $e;
        }

        $spec    = self::readFromJson($fileContent, $baseType);
        $context = new ReferenceContext($spec, $fileName);
        $spec->setReferenceContext($context);
        if ($resolveReferences !== false) {
            if (is_string($resolveReferences)) {
                $context->mode = $resolveReferences;
            }

            if ($spec instanceof DocumentContextInterface) {
                $spec->setDocumentContext($spec, new JsonPointer(''));
            }

            $spec->resolveReferences();
        }

        return $spec;
    }

    /**
     * Populate OpenAPI spec object from YAML file.
     *
     * @param string      $fileName          the file name of the file to be read.
     *               If `$resolveReferences` is true (the default), this should be an absolute URL, a `file://` URI or
     *               an absolute path to allow resolving relative path references.
     * @param string      $baseType          the base Type to instantiate. This must be an instance of [[SpecObjectInterface]].
     *               The default is [[OpenApi]] which is the base type of a OpenAPI specification file.
     *               You may choose a different type if you instantiate objects from sub sections of a specification.
     * @param bool|string $resolveReferences whether to automatically resolve references in the specification.
     * If `true`, all [[Reference]] objects will be replaced with their referenced spec objects by calling
     * [[SpecObjectInterface::resolveReferences()]].
     * Since version 1.5.0 this can be a string indicating the reference resolving mode:
     * - `inline` only resolve references to external files.
     * - `all` resolve all references except recursive references.
     * @phpstan-param class-string<T> $baseType
     *
     * @return SpecObjectInterface|OpenApi the OpenApi object instance.
     * The type of the returned object depends on the `$baseType` argument.
     * @phpstan-return T
     *
     * @throws TypeErrorException in case invalid spec data is supplied.
     * @throws UnresolvableReferenceException in case references could not be resolved.
     * @throws IOException when the file is not readable.
     *
     * @phpstan-template T of SpecObjectInterface
     */
    public static function readFromYamlFile(
        string $fileName,
        string $baseType = OpenApi::class,
        bool|string $resolveReferences = true,
    ): SpecObjectInterface {
        $fileContent = file_get_contents($fileName);
        if ($fileContent === false) {
            $e           = new IOException(sprintf("Failed to read file: '%s'", $fileName));
            $e->fileName = $fileName;

            throw $e;
        }

        $spec    = self::readFromYaml($fileContent, $baseType);
        $context = new ReferenceContext($spec, $fileName);
        $spec->setReferenceContext($context);
        if ($resolveReferences !== false) {
            if (is_string($resolveReferences)) {
                $context->mode = $resolveReferences;
            }

            if ($spec instanceof DocumentContextInterface) {
                $spec->setDocumentContext($spec, new JsonPointer(''));
            }

            $spec->resolveReferences();
        }

        return $spec;
    }
}
