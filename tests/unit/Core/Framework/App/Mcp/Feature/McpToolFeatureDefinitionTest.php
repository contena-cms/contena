<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Mcp\Feature;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Feature\TranslatedString;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Contena\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Contena\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpToolFeatureDefinition;
use Contena\Core\Framework\Util\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(McpToolFeatureDefinition::class)]
class McpToolFeatureDefinitionTest extends TestCase
{
    private McpToolFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new McpToolFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('mcp_tool', $this->definition->getType());
        static::assertSame(McpToolConfig::class, $this->definition->getConfigClass());
    }

    public function testExtractReturnsEmptyWhenNoMcpFile(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testExtractReadsToolsFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(2, $configs);
        $config = $configs[0];
        static::assertSame('sync-blogs', $config->name);
        static::assertSame('https://app.example.com/mcp/sync', $config->url);
        static::assertSame(['blog:read', 'blog:update'], $config->requiredPrivileges);
        static::assertSame(
            ['since' => ['type' => 'string', 'description' => 'ISO 8601 date', 'required' => true], 'limit' => ['type' => 'integer', 'description' => 'Maximum number of blogs']],
            $config->inputSchema,
        );
        static::assertSame('Sync blogs', $config->label->forLocale('en-GB'));
        static::assertSame('同步文章', $config->label->forLocale('zh-CN'));
        static::assertSame('Synchronize blogs from an external content source', $config->description->forLocale('en-GB'));
    }

    public function testFromAppFillsMissingDefaultLocaleTranslationFromFallback(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'fr-FR',
        );

        static::assertCount(2, $configs);
        static::assertSame('Sync blogs', $configs[0]->label->forLocale('fr-FR'));
        static::assertSame('Synchronize blogs from an external content source', $configs[0]->description->forLocale('fr-FR'));
    }

    public function testFromAppPassesWhenManifestGrantsRequiredPrivileges(): void
    {
        $configs = $this->definition->fromApp(
            $this->manifest(Permissions::fromArray([
                'permissions' => ['blog' => ['read', 'update']],
                'additionalPrivileges' => [],
            ])),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(2, $configs);
    }

    public function testFromAppRejectsRequiredPrivilegeMissingFromManifestPermissions(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches('/requires "blog:update" but it is not declared in <permissions>/');

        $this->definition->fromApp(
            $this->manifest(Permissions::fromArray([
                'permissions' => ['blog' => ['read']],
                'additionalPrivileges' => [],
            ])),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );
    }

    public function testFromAppSkipsPrivilegeValidationWhenManifestHasNoPermissions(): void
    {
        $configs = $this->definition->fromApp(
            $this->manifest(null),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(2, $configs);
    }

    public function testPayloadRoundTripIgnoresStored(): void
    {
        $declared = new McpToolConfig(
            'sync-blogs',
            'https://app.example.com/mcp/sync',
            ['blog:read'],
            ['since' => ['type' => 'string', 'required' => true]],
            new TranslatedString(['en-GB' => 'Sync blogs']),
            new TranslatedString(['en-GB' => 'Synchronize blogs from an external content source']),
        );

        $stored = new McpToolConfig('sync-blogs', 'https://stale.example.com', [], null, new TranslatedString(['en-GB' => 'Old']), new TranslatedString([]));

        $payload = $this->definition->toPayload($declared, $stored);
        $hydrated = $this->definition->fromPayload($payload);

        static::assertEquals($declared, $hydrated);
    }

    private function manifest(?Permissions $permissions): Manifest
    {
        $metadata = Metadata::fromArray([
            'label' => ['en-GB' => 'MyApp'],
            'description' => [],
            'name' => 'MyApp',
            'author' => 'contena AG',
            'copyright' => '(c) contena AG',
            'license' => 'MIT',
            'version' => '1.0.0',
            'privacyPolicyExtensions' => [],
        ]);

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);
        $manifest->method('getPermissions')->willReturn($permissions);

        return $manifest;
    }
}
