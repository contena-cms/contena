<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\ApiDefinition\Generator;

/**
 * @internal
 */
interface ChannelApiSchemaMigrationScopeProviderInterface
{
    public const SERVICE_TAG = 'contena.channel_api_schema_migration.scope_provider';

    public function getScope(): string;

    /**
     * @return list<string>
     */
    public function getDefinitionClassPrefixes(): array;

    /**
     * @return list<string>
     */
    public function getSchemaPaths(): array;

    public function getAllowlistPath(): string;

    public function includesAllDefinitions(): bool;
}
