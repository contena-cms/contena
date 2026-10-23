<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\ApiDefinition\Generator;


/**
 * @internal
 */
interface StoreApiSchemaMigrationScopeProviderInterface
{
    public const SERVICE_TAG = 'contena.store_api_schema_migration.scope_provider';

    public function getScope(): string;

    /**
     * @return list<string>
     */
    public function getDefinitionClassPrefixes(): array;

    /**
     * @return list<string>
     */
    public function getSchemaPaths(): array;

    public function includesAllDefinitions(): bool;
}
