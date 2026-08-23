<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\ApiDefinition\Generator;

use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;

/**
 * @internal
 */
final class AllChannelApiSchemaMigrationScopeProvider implements ChannelApiSchemaMigrationScopeProviderInterface
{
    public const SCOPE = 'all';

    private readonly string $schemaPath;

    private readonly string $allowlistPath;

    public function __construct(
        private readonly BundleSchemaPathCollection $bundleSchemaPathCollection,
        ?string $schemaPath = null,
        ?string $allowlistPath = null,
    ) {
        $this->schemaPath = $schemaPath ?? __DIR__ . '/Schema/ChannelApi';
        $this->allowlistPath = $allowlistPath ?? __DIR__ . '/ChannelApiPhpGeneratedSchemaAllowlist.json';
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
            $this->bundleSchemaPathCollection->getSchemaPaths(DefinitionService::CHANNEL_API, null),
        ));
    }

    public function getAllowlistPath(): string
    {
        return $this->allowlistPath;
    }

    public function includesAllDefinitions(): bool
    {
        return true;
    }
}
