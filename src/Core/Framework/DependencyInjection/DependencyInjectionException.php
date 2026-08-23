<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class DependencyInjectionException extends HttpException
{
    public const string PROJECT_DIR_IS_NOT_A_STRING = 'FRAMEWORK__PROJECT_DIR_IS_NOT_A_STRING';
    public const string BUNDLES_METADATA_IS_NOT_AN_ARRAY = 'FRAMEWORK__BUNDLES_METADATA_IS_NOT_AN_ARRAY';
    public const string TAGGED_SERVICE_HAS_WRONG_TYPE = 'FRAMEWORK__TAGGED_SERVICE_HAS_WRONG_TYPE';
    public const string PARAMETER_HAS_WRONG_TYPE = 'FRAMEWORK__PARAMETER_HAS_WRONG_TYPE';
    public const string MISSING_ASSIGNABLE_DEFINITION = 'FRAMEWORK__MISSING_ASSIGNABLE_DEFINITION';
    public const string ROOT_SOURCE_NAMESPACE_COLLISION = 'FRAMEWORK__ROOT_SOURCE_NAMESPACE_COLLISION';
    public const string DATA_LOADER_RESERVED_SOURCE = 'FRAMEWORK__DATA_LOADER_RESERVED_SOURCE';
    public const string DATA_LOADER_CONFIG_KEY_DUPLICATE = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_DUPLICATE';
    public const string DATA_LOADER_CONFIG_KEY_INVALID_TYPE = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_INVALID_TYPE';
    public const string DATA_LOADER_CONFIG_KEY_UNKNOWN_TYPE = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_UNKNOWN_TYPE';
    public const string DATA_LOADER_CONFIG_KEY_DEFAULT_MISMATCH = 'FRAMEWORK__DATA_LOADER_CONFIG_KEY_DEFAULT_MISMATCH';
    public const string DATA_LOADER_RESERVED_CONFIG_KEY = 'FRAMEWORK__DATA_LOADER_RESERVED_CONFIG_KEY';
    private const string MCP_DUPLICATE_TOOL_NAME = 'FRAMEWORK__MCP_DUPLICATE_TOOL_NAME';
    private const string MCP_UNKNOWN_TOOL_DEPENDENCY = 'FRAMEWORK__MCP_UNKNOWN_TOOL_DEPENDENCY';

    public static function definitionNotFound(string $entity): DefinitionNotFoundException
    {
        return new DefinitionNotFoundException($entity);
    }

    public static function projectDirNotInContainer(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROJECT_DIR_IS_NOT_A_STRING,
            'Container parameter "kernel.project_dir" needs to be a string'
        );
    }

    public static function bundlesMetadataIsNotAnArray(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::BUNDLES_METADATA_IS_NOT_AN_ARRAY,
            'Container parameter "kernel.bundles_metadata" needs to be an array'
        );
    }

    public static function taggedServiceHasWrongType(string $service, string $tag, string $type): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TAGGED_SERVICE_HAS_WRONG_TYPE,
            \sprintf('Service "%s" is tagged as "%s" and must therefore be of type "%s".', $service, $tag, $type)
        );
    }

    public static function missingAssignableDefinition(string $service, string $tag): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_ASSIGNABLE_DEFINITION,
            \sprintf(
                'Service "%s" is tagged as "%s" but none of its constructor arguments reference an "%s" subclass.',
                $service,
                $tag,
                AbstractContentLayoutAssignableDefinition::class
            )
        );
    }

    public static function rootSourceNamespaceCollision(string $rootSource): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ROOT_SOURCE_NAMESPACE_COLLISION,
            \sprintf(
                'The content-layout entity type "%s" collides with a reserved root-source id (a section id or "none"). '
                . 'Entity-type ids, section ids, and "none" must remain disjoint so RootSourceRegistry resolves each to one source.',
                $rootSource
            )
        );
    }

    public static function dataLoaderReservedSource(string $loaderClass, string $source): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_RESERVED_SOURCE,
            \sprintf(
                'Data loader "%s" uses the reserved source name "%s". The names "loader" and "config" are reserved by the binding sugar grammar and cannot name a loader source.',
                $loaderClass,
                $source
            )
        );
    }

    public static function dataLoaderConfigKeyDuplicate(string $loaderClass, string $key): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_DUPLICATE,
            \sprintf('Data loader "%s" declares the config key "%s" more than once in its configSpecification().', $loaderClass, $key)
        );
    }

    public static function dataLoaderConfigKeyInvalidType(string $loaderClass, string $key, string $kind, string $type): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_INVALID_TYPE,
            \sprintf('Config key "%s" of data loader "%s" has kind "%s", which requires type "string", got "%s".', $key, $loaderClass, $kind, $type)
        );
    }

    /**
     * @param list<string> $declarableTypes
     */
    public static function dataLoaderConfigKeyUnknownType(string $loaderClass, string $key, string $type, array $declarableTypes): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_UNKNOWN_TYPE,
            \sprintf(
                'Config key "%s" of data loader "%s" declares the unknown type "%s". Declarable types: "%s".',
                $key,
                $loaderClass,
                $type,
                implode('", "', $declarableTypes)
            )
        );
    }

    public static function dataLoaderConfigKeyDefaultMismatch(string $loaderClass, string $key, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_CONFIG_KEY_DEFAULT_MISMATCH,
            \sprintf('Config key "%s" of data loader "%s" has an incoherent default: %s.', $key, $loaderClass, $reason)
        );
    }

    public static function dataLoaderReservedConfigKey(string $loaderClass, string $key): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_RESERVED_CONFIG_KEY,
            \sprintf(
                'Data loader "%s" declares the reserved config key "%s". The names "loader" and "config" are reserved and cannot name a config key.',
                $loaderClass,
                $key
            )
        );
    }

    public static function parameterHasWrongType(string $parameter, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PARAMETER_HAS_WRONG_TYPE,
            \sprintf('Parameter "%s" should be: "%s". Got: "%s"', $parameter, $expectedType, $actualType)
        );
    }

    public static function unknownMcpToolDependency(string $dependentTool, string $missingDependency): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MCP_UNKNOWN_TOOL_DEPENDENCY,
            'MCP tool "{{ dependentTool }}" declares a dependency on "{{ missingDependency }}" which is not registered. Check the tool name or register the missing tool.',
            ['dependentTool' => $dependentTool, 'missingDependency' => $missingDependency],
        );
    }

    public static function duplicateMcpToolName(string $toolName, string $existingServiceId, string $newServiceId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MCP_DUPLICATE_TOOL_NAME,
            'Duplicate MCP tool name "{{ toolName }}": services "{{ existingServiceId }}" and "{{ newServiceId }}" conflict. Use a unique namespace prefix (e.g. "your-plugin-tool-name").',
            ['toolName' => $toolName, 'existingServiceId' => $existingServiceId, 'newServiceId' => $newServiceId],
        );
    }
}
