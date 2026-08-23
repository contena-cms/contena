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
        'Contena\\Frontend\\',
    ];

    private readonly string $schemaPath;

    private readonly string $allowlistPath;

    public function __construct(
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
        return self::PLATFORM_NAMESPACES;
    }

    public function getSchemaPaths(): array
    {
        return [$this->schemaPath];
    }

    public function getAllowlistPath(): string
    {
        return $this->allowlistPath;
    }

    public function includesAllDefinitions(): bool
    {
        return false;
    }
}
