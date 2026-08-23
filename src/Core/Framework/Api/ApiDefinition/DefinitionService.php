<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\ApiDefinition;

use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @phpstan-type Api DefinitionService::API|DefinitionService::CHANNEL_API
 * @phpstan-type ApiType DefinitionService::TYPE_JSON_API|DefinitionService::TYPE_JSON
 * @phpstan-type OpenApiSpec  array{paths: array<string,array<mixed>>, components: array<mixed>}
 * @phpstan-type ApiSchema array<string, array{name: string, translatable: list<string>, properties: array<string, mixed>}|array{entity: string, properties: array<string, mixed>, write-protected: bool, read-protected: bool}>
 */
class DefinitionService
{
    final public const string API = 'api';
    final public const string CHANNEL_API = 'channel-api';
    final public const string TYPE_JSON_API = 'jsonapi';

    final public const string TYPE_JSON = 'json';

    /**
     * @var ApiDefinitionGeneratorInterface[]
     */
    private readonly array $generators;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        ApiDefinitionGeneratorInterface ...$generators
    ) {
        $this->generators = $generators;
    }

    /**
     * @param Api $type
     * @param ApiType $apiType
     *
     * @return OpenApiSpec
     */
    public function generate(string $format = 'openapi-3', string $type = self::API, string $apiType = self::TYPE_JSON_API, ?string $bundleName = null): array
    {
        return $this->getGenerator($format, $type)->generate($this->getDefinitions($type), $type, $apiType, $bundleName);
    }

    /**
     * @param Api $type
     *
     * @return ApiSchema
     */
    public function getSchema(string $format = 'openapi-3', string $type = self::API): array
    {
        return $this->getGenerator($format, $type)->getSchema($this->getDefinitions($type));
    }

    /**
     * @return ApiType|null
     */
    public function toApiType(string $apiType): ?string
    {
        if ($apiType !== self::TYPE_JSON_API && $apiType !== self::TYPE_JSON) {
            return null;
        }

        return $apiType;
    }

    private function getGenerator(string $format, string $type): ApiDefinitionGeneratorInterface
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($format, $type)) {
                return $generator;
            }
        }

        throw ApiException::apiDefinitionGeneratorNotFound($format);
    }

    /**
     * @return array<string, EntityDefinition>
     */
    private function getDefinitions(string $type): array
    {
        if ($type === self::API) {
            return $this->definitionRegistry->getDefinitions();
        }

        if ($type === self::CHANNEL_API) {
            return $this->definitionRegistry->getDefinitions();
        }

        throw ApiException::apiDefinitionGeneratorNotFound($type);
    }
}
