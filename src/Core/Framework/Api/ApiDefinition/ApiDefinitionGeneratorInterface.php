<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\ApiDefinition;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;

/**
 * @internal
 *
 * @phpstan-import-type Api from DefinitionService
 * @phpstan-import-type ApiType from DefinitionService
 * @phpstan-import-type OpenApiSpec from DefinitionService
 * @phpstan-import-type ApiSchema from DefinitionService
 */
interface ApiDefinitionGeneratorInterface
{
    public function supports(string $format, string $api): bool;

    /**
     * @param array<string, EntityDefinition>|array<string, EntityDefinition&ChannelDefinitionInterface> $definitions
     * @param Api $api
     * @param ApiType $apiType
     *
     * @return OpenApiSpec
     */
    public function generate(array $definitions, string $api, string $apiType, ?string $bundleName): array;

    /**
     * @param array<string, EntityDefinition>|array<string, EntityDefinition&ChannelDefinitionInterface> $definitions
     *
     * @return ApiSchema
     */
    public function getSchema(array $definitions): array;
}
