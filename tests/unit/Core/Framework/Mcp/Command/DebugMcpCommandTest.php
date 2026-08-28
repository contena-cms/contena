<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Command;

use Mcp\Capability\Registry;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Tool;
use Mcp\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlist;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Contena\Core\Framework\Mcp\Command\DebugMcpCommand;
use Contena\Core\Framework\Mcp\McpCapabilityCatalog;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(DebugMcpCommand::class)]
#[CoversClass(McpCapabilityCatalog::class)]
class DebugMcpCommandTest extends TestCase
{
    public function testListsCapabilitiesForBothApiScopes(): void
    {
        $adminRegistry = new Registry();
        $adminRegistry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');
        $channelRegistry = new Registry();
        $channelRegistry->registerTool(new Tool('channel-tool', null, self::inputSchema(), null, null), 'Acme\\ChannelTool');

        $tester = new CommandTester($this->makeCommand($adminRegistry, $channelRegistry));
        $tester->execute([]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('Admin API (/api/_mcp)', $tester->getDisplay());
        static::assertStringContainsString('Channel API (/channel-api/_mcp)', $tester->getDisplay());
        static::assertStringContainsString('admin-tool', $tester->getDisplay());
        static::assertStringContainsString('channel-tool', $tester->getDisplay());
    }

    public function testToolsFilterOmitsPromptsAndResources(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');
        $registry->registerPrompt(new Prompt('admin-prompt', null, 'Prompt', []), 'Acme\\Prompt', []);
        $registry->registerResource(
            new ResourceDefinition('contena://test', 'admin-resource', null, 'Resource', null, null, null),
            'Acme\\Resource',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['--tools' => true]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('admin-tool', $tester->getDisplay());
        static::assertStringNotContainsString('admin-prompt', $tester->getDisplay());
        static::assertStringNotContainsString('admin-resource', $tester->getDisplay());
    }

    public function testDetailViewFindsCapabilityByResourceUri(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new ResourceDefinition('contena://entities', 'entities', null, 'All entities', null, null, null),
            'Acme\\EntitiesResource',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'contena://entities']);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('contena://entities', $tester->getDisplay());
        static::assertStringContainsString('All entities', $tester->getDisplay());
    }

    public function testDetailViewRendersToolWithoutRequiredSchemaKey(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool(
                'my-tool',
                null,
                ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]],
                'Does things',
                null,
            ),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('limit', $tester->getDisplay());
        static::assertStringContainsString('optional', $tester->getDisplay());
    }

    public function testDetailViewRendersToolWithNonArrayRequiredSchemaValue(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool(
                'my-tool',
                null,
                ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']], 'required' => 'invalid'],
                'Does things',
                null,
            ),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('limit', $tester->getDisplay());
        static::assertStringContainsString('optional', $tester->getDisplay());
    }

    public function testIntegrationAllowlistOnlyFiltersAdminScope(): void
    {
        $adminRegistry = new Registry();
        $adminRegistry->registerTool(new Tool('admin-visible', null, self::inputSchema(), null, null), 'Acme\\Visible');
        $adminRegistry->registerTool(new Tool('admin-hidden', null, self::inputSchema(), null, null), 'Acme\\Hidden');
        $channelRegistry = new Registry();
        $channelRegistry->registerTool(new Tool('channel-tool', null, self::inputSchema(), null, null), 'Acme\\Channel');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: ['admin-visible'], resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($adminRegistry, $channelRegistry, $allowlistProvider));
        $tester->execute(['--integration' => 'SWIA-test']);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('admin-visible', $tester->getDisplay());
        static::assertStringNotContainsString('admin-hidden', $tester->getDisplay());
        static::assertStringContainsString('channel-tool', $tester->getDisplay());
    }

    public function testUnknownScopeIsRejected(): void
    {
        $tester = new CommandTester($this->makeCommand(new Registry()));
        $tester->execute(['--scope' => 'unknown']);

        static::assertSame(2, $tester->getStatusCode());
        static::assertStringContainsString('Invalid scope "unknown"', $tester->getDisplay());
    }

    private function makeCommand(
        Registry $adminRegistry,
        ?Registry $channelRegistry = null,
        ?McpAllowlistProvider $allowlistProvider = null,
    ): DebugMcpCommand {
        $channelRegistry ??= new Registry();
        if ($allowlistProvider === null) {
            $allowlistProvider = static::createStub(McpAllowlistProvider::class);
            $allowlistProvider->method('forAccessKey')->willReturn(McpAllowlist::unrestricted());
        }

        return new DebugMcpCommand(
            Server::builder()->setRegistry($adminRegistry),
            $adminRegistry,
            $allowlistProvider,
            new McpCapabilityCatalog($adminRegistry),
            Server::builder()->setRegistry($channelRegistry),
            $channelRegistry,
            new McpCapabilityCatalog($channelRegistry),
        );
    }

    /**
     * @return array{type: 'object', properties: array<string, mixed>, required: list<string>}
     */
    private static function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'required' => []];
    }
}
