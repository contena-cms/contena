<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\ApiDefinition\Generator;

use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;

/**
 * @internal
 */
final class AllChannelApiSchemaMigrationScopeProvider implements ChannelApiSchemaMigrationScopeProviderInterface
{
    public const SCOPE = 'all';

    private readonly string $schemaPath;

    public function __construct(
        private readonly BundleSchemaPathCollection $bundleSchemaPathCollection,
        ?string $schemaPath = null,
    )
    {
        $this->schemaPath = $schemaPath ?? __DIR__ . '/Schema/ChannelApi';
    }

    public function getScope(): string
    {
        return self::SCOPE;
    }

    public function getDefinitionClassPrefixes(): array
    {
        return [];
    }

    public function getSchemaPaths(): array
    {
        return array_values(array_merge(
            [$this->schemaPath],
            $this->bundleSchemaPathCollection->getSchemaPaths(DefinitionService::STORE_API, null),
        ));
    }

    public function includesAllDefinitions(): bool
    {
        return true;
    }
}
