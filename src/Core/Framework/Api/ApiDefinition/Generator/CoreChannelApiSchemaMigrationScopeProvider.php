<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\ApiDefinition\Generator;


/**
 * @internal
 */
final class CoreChannelApiSchemaMigrationScopeProvider implements ChannelApiSchemaMigrationScopeProviderInterface
{
    public const SCOPE = 'core';

    private const PLATFORM_NAMESPACES = [
        'Contena\\Administration\\',
        'Contena\\Core\\',
        'Contena\\Elasticsearch\\',
        'Contena\\Frontend\\',
    ];

    private readonly string $schemaPath;

    public function __construct(
        ?string $schemaPath = null,
    ) {
        $this->schemaPath = $schemaPath ?? __DIR__ . '/Schema/ChannelApi';
    }

    public function getScope(): string
    {
        return self::SCOPE;
    }

    public function getDefinitionClassPrefixes(): array
    {
        return self::PLATFORM_NAMESPACES;
    }

    public function getSchemaPaths(): array
    {
        return [$this->schemaPath];
    }

    public function includesAllDefinitions(): bool
    {
        return false;
    }
}
