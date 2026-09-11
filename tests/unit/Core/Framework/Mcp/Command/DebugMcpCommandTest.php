<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Command;

use Contena\Core\Framework\DependencyInjection\CompilerPass\McpDebugCommandCompilerPass;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlist;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Contena\Core\Framework\Mcp\Command\DebugMcpCommand;
use Contena\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Contena\Core\Framework\Mcp\McpCapabilityCatalog;
use Mcp\Capability\Registry;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Tool;
use Mcp\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(DebugMcpCommand::class)]
#[CoversClass(McpCapabilityCatalog::class)]
class DebugMcpCommandTest extends TestCase
{
    public function testOutputsSectionHeaders(): void
    {
        $command = $this->makeCommand(new Registry());
        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Tools', $output);
        // Prompts and resources are listed by the bundle's command; this one points at it.
        static::assertStringContainsString('debug:mcp:native', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testEmptyRegistryShowsNoCapabilitiesMessages(): void
    {
        $command = $this->makeCommand(new Registry());
        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('No tools registered', $output);
    }

    public function testToolIsRenderedCompactInListWithoutDescription(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', null, self::inputSchema(), 'Does things', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('my-tool', $output);
        static::assertStringContainsString('Acme\\MyTool', $output);
        static::assertStringNotContainsString('Does things', $output);
        static::assertStringNotContainsString('Description', $output);
    }

    public function testAppProvidedToolShowsAppProvidedSource(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('McpHelloWorld-hello', null, self::inputSchema(), 'Says hello', null),
            static function (): string { return 'hello'; },
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('McpHelloWorld-hello', $output);
        static::assertStringContainsString('(app-provided)', $output);
    }

    public function testArrayHandlerShowsClassAndMethod(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('array-tool', null, self::inputSchema(), null, null),
            ['Acme\\MyTool', 'handle'],
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute([]);

        static::assertStringContainsString('Acme\\MyTool::handle', $tester->getDisplay());
    }

    public function testDetailViewShowsTitleWhenSet(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', 'My Human-Readable Tool', self::inputSchema(), 'Does things', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        static::assertStringContainsString('My Human-Readable Tool', $tester->getDisplay());
    }

    /**
     * Title is always rendered, with a dash when the capability carries none, so the block keeps the
     * same shape and the rows below it do not shift.
     */
    public function testDetailViewShowsADashWhenTitleIsNull(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', null, self::inputSchema(), 'Does things', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        static::assertMatchesRegularExpression('/Title\s+-/', $tester->getDisplay());
    }

    /**
     * The detail block reads top-down: what it is, where it lives, what governs reaching it, and only
     * then how it is implemented. Handler is last because it is the longest value and the least
     * common reason to open this view.
     */
    public function testDetailViewOrdersMetadataFromIdentityToImplementation(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', 'My Tool', self::inputSchema(), 'Does things', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        $output = $tester->getDisplay();
        $positions = [];
        foreach (['Title', 'Type', 'Scope', 'Group', 'Handler'] as $label) {
            $position = mb_strpos($output, $label);
            static::assertNotFalse($position, \sprintf('The detail view is missing the "%s" row.', $label));
            $positions[] = $position;
        }

        $sorted = $positions;
        sort($sorted);
        static::assertSame($sorted, $positions, 'Detail rows must read Title, Type, Scope, Group, Handler.');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedRequiredSchemaProvider(): iterable
    {
        yield 'required key omitted, as the SDK SchemaGenerator does when no parameter is required' => [
            ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]],
        ];

        yield 'required value is not an array, as unvalidated third-party registrations may carry' => [
            ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']], 'required' => 'invalid'],
        ];
    }

    /**
     * @param array<string, mixed> $inputSchema
     */
    #[DataProvider('malformedRequiredSchemaProvider')]
    public function testDetailViewRendersToolWithMalformedRequiredSchema(array $inputSchema): void
    {
        $registry = new Registry();
        $registry->registerTool(
            // @phpstan-ignore argument.type (malformed schemas are not bound by the ToolInputSchema type alias at runtime)
            new Tool('my-tool', null, $inputSchema, 'Does things', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        static::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        static::assertStringContainsString('limit', $output);
        static::assertStringContainsString('optional', $output);
    }

    public function testDetailViewShowsToolDescriptionAndSource(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', null, self::inputSchema(), 'Does things for you', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('my-tool', $output);
        static::assertStringContainsString('Does things for you', $output);
        static::assertStringContainsString('Acme\\MyTool', $output);
        static::assertStringContainsString('tool', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewShowsToolParameters(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'entity' => ['type' => 'string', 'description' => 'Entity type to search'],
                'limit' => ['type' => 'int', 'description' => 'Page size', 'default' => 25],
            ],
            'required' => ['entity'],
        ];
        $registry = new Registry();
        $registry->registerTool(
            new Tool('search-tool', null, $schema, 'Searches entities', null),
            'Acme\\SearchTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'search-tool']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('entity', $output);
        static::assertStringContainsString('required', $output);
        static::assertStringContainsString('limit', $output);
        static::assertStringContainsString('optional', $output);
        static::assertStringContainsString('Default: 25', $output);
    }

    public function testDetailViewShowsPromptDescriptionAndSource(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('my-prompt', null, 'Explains everything', []),
            'Acme\\MyPrompt',
            [],
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-prompt']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('my-prompt', $output);
        static::assertStringContainsString('Explains everything', $output);
        static::assertStringContainsString('Acme\\MyPrompt', $output);
        static::assertStringContainsString('prompt', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewShowsResourceUriAndDescription(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new ResourceDefinition('contena://test', 'my-resource', null, 'A helpful resource', null, null, null),
            'Acme\\MyResource',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-resource']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('my-resource', $output);
        static::assertStringContainsString('contena://test', $output);
        static::assertStringContainsString('A helpful resource', $output);
        static::assertStringContainsString('resource', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewCanLookUpResourceByUri(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new ResourceDefinition('contena://entities', 'entities', null, 'All entity types', null, null, null),
            'Acme\\EntitiesResource',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'contena://entities']);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('contena://entities', $tester->getDisplay());
    }

    public function testToolsFilterShowsOnlyTools(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('my-tool', null, self::inputSchema(), 'Tool desc', null), 'Acme\\MyTool');
        $registry->registerPrompt(new Prompt('my-prompt', null, 'Prompt desc', []), 'Acme\\MyPrompt', []);
        $registry->registerResource(new ResourceDefinition('contena://test', 'my-resource', null, 'Resource desc', null, null, null), 'Acme\\MyResource');

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['--tools' => true]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Tools', $output);
        static::assertStringContainsString('my-tool', $output);
        static::assertStringNotContainsString('Prompts', $output);
        static::assertStringNotContainsString('Resources', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testIntegrationOptionWithNullAllowlistShowsAllToolsAndNote(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA');
        $registry->registerTool(new Tool('tool-b', null, self::inputSchema(), null, null), 'Acme\\ToolB');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: null, resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($registry, allowlistProvider: $allowlistProvider));
        $tester->execute(['--integration' => 'CTIA-test-key']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('no tool restriction', $output);
        static::assertStringContainsString('tool-a', $output);
        static::assertStringContainsString('tool-b', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testIntegrationOptionFiltersToAllowedTools(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA');
        $registry->registerTool(new Tool('tool-b', null, self::inputSchema(), null, null), 'Acme\\ToolB');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: ['tool-a'], resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($registry, allowlistProvider: $allowlistProvider));
        $tester->execute(['--integration' => 'CTIA-restricted']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('1/2 allowed', $output);
        static::assertStringContainsString('tool-a', $output);
        static::assertStringNotContainsString('tool-b', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testIntegrationOptionWithEmptyAllowlistShowsNoTools(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: [], resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($registry, allowlistProvider: $allowlistProvider));
        $tester->execute(['--integration' => 'CTIA-empty']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('0/1 allowed', $output);
        static::assertStringNotContainsString('tool-a', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewShowsDependenciesAndStaticPrivileges(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('contena-entity-delete', null, self::inputSchema(), 'Delete entities', null),
            'Acme\\DeleteTool',
        );

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            ['contena-entity-delete' => ['contena-entity-search']],
            ['contena-entity-delete' => ['static' => ['system_config:read'], 'entityParam' => null, 'operations' => []]],
        );

        $tester = new CommandTester($this->makeCommand($registry, catalog: $catalog));
        $tester->execute(['name' => 'contena-entity-delete']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Dependencies', $output);
        static::assertStringContainsString('contena-entity-search', $output);
        static::assertStringContainsString('Privileges', $output);
        static::assertStringContainsString('system_config:read', $output);
    }

    public function testDetailViewShowsDynamicPrivilegesWithEntityParam(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('contena-entity-search', null, self::inputSchema(), 'Search entities', null),
            'Acme\\SearchTool',
        );

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            [],
            ['contena-entity-search' => ['static' => [], 'entityParam' => 'entity', 'operations' => ['read']]],
        );

        $tester = new CommandTester($this->makeCommand($registry, catalog: $catalog));
        $tester->execute(['name' => 'contena-entity-search']);

        static::assertStringContainsString('<entity>:read', $tester->getDisplay());
    }

    public function testDetailViewShowsPromptArguments(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('my-prompt', null, 'Explains things', [
                new PromptArgument('topic', 'What to explain', true),
                new PromptArgument('depth', 'Detail level', false),
            ]),
            'Acme\\MyPrompt',
            [],
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-prompt']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Arguments', $output);
        static::assertStringContainsString('topic', $output);
        static::assertStringContainsString('required', $output);
        static::assertStringContainsString('depth', $output);
        static::assertStringContainsString('optional', $output);
    }

    public function testDetailViewShowsResourceMimeType(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new ResourceDefinition('contena://json', 'json-resource', null, 'JSON resource', 'application/json', null, null),
            'Acme\\JsonResource',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'json-resource']);

        static::assertStringContainsString('application/json', $tester->getDisplay());
    }

    public function testListShowsPrivilegesColumn(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('contena-entity-search', null, self::inputSchema(), 'Search entities', null),
            'Acme\\SearchTool',
        );

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            [],
            ['contena-entity-search' => ['static' => ['system_config:read'], 'entityParam' => 'entity', 'operations' => ['read']]],
        );

        $tester = new CommandTester($this->makeCommand($registry, catalog: $catalog));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Privileges', $output);
        static::assertStringContainsString('system_config:read', $output);
        static::assertStringContainsString('<entity>:read', $output);
    }

    public function testListShowsGroupColumn(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('contena-entity-search', null, self::inputSchema(), 'Search entities', null),
            'Acme\\SearchTool',
        );

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            toolGroups: ['contena-entity-search' => 'catalogue'],
        );

        $tester = new CommandTester($this->makeCommand($registry, catalog: $catalog));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Group', $output);
        static::assertStringContainsString('catalogue', $output);
    }

    public function testDetailViewReturnsFailureForUnknownName(): void
    {
        $tester = new CommandTester($this->makeCommand(new Registry()));
        $tester->execute(['name' => 'does-not-exist']);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('No capability found with name \'does-not-exist\'', $tester->getDisplay());
    }

    public function testDetailViewSkipsNonArrayPropertyDefinitions(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'valid-param' => ['type' => 'string', 'description' => 'A valid param'],
                'bad-param' => 'not-an-array',
            ],
            'required' => ['valid-param'],
        ];
        $registry = new Registry();
        $registry->registerTool(
            new Tool('schema-tool', null, $schema, 'Does things', null),
            'Acme\\SchemaTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'schema-tool']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('valid-param', $output);
        static::assertStringNotContainsString('bad-param', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testArrayHandlerWithObjectInstanceShowsClassName(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('object-tool', null, self::inputSchema(), null, null),
            [new \stdClass(), 'handle'],
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute([]);

        static::assertStringContainsString('stdClass::handle', $tester->getDisplay());
    }

    public function testChannelApiCapabilitiesAreListedAlongsideAdminOnesByDefault(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');

        $channelApiRegistry = new Registry();
        $channelApiRegistry->registerTool(new Tool('channel-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $tester = new CommandTester($this->makeCommand($registry, channelApiRegistry: $channelApiRegistry));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Admin API (/api/_mcp)', $output);
        static::assertStringContainsString('admin-tool', $output);
        static::assertStringContainsString('Channel API (/channel-api/_mcp)', $output);
        static::assertStringContainsString('channel-tool', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testSectionHeadingsNameTheirScope(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');

        $channelApiRegistry = new Registry();
        $channelApiRegistry->registerTool(new Tool('channel-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $tester = new CommandTester($this->makeCommand($registry, channelApiRegistry: $channelApiRegistry));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Tools (1) [Admin API]', $output);
        static::assertStringContainsString('Tools (1) [Channel API]', $output);
    }

    public function testAllowlistCountsStayOnTheAdminSectionHeading(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA');
        $registry->registerTool(new Tool('tool-b', null, self::inputSchema(), null, null), 'Acme\\ToolB');

        $channelApiRegistry = new Registry();
        $channelApiRegistry->registerTool(new Tool('channel-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: ['tool-a'], resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($registry, $allowlistProvider, channelApiRegistry: $channelApiRegistry));
        $tester->execute(['--integration' => 'CTIA-restricted']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Tools (1/2 allowed) [Admin API]', $output);
        static::assertStringContainsString('Tools (1) [Channel API]', $output);
    }

    public function testScopeOptionLimitsOutputToChannelApi(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');

        $channelApiRegistry = new Registry();
        $channelApiRegistry->registerTool(new Tool('channel-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $tester = new CommandTester($this->makeCommand($registry, channelApiRegistry: $channelApiRegistry));
        $tester->execute(['--scope' => 'channel-api']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('channel-tool', $output);
        static::assertStringNotContainsString('admin-tool', $output);
        static::assertStringNotContainsString('Admin API (/api/_mcp)', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testScopeOptionLimitsOutputToAdminApi(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');

        $channelApiRegistry = new Registry();
        $channelApiRegistry->registerTool(new Tool('channel-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $tester = new CommandTester($this->makeCommand($registry, channelApiRegistry: $channelApiRegistry));
        $tester->execute(['--scope' => 'api']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('admin-tool', $output);
        static::assertStringNotContainsString('channel-tool', $output);
        static::assertStringNotContainsString('Channel API (/channel-api/_mcp)', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testUnknownScopeIsRejected(): void
    {
        $tester = new CommandTester($this->makeCommand(new Registry()));
        $tester->execute(['--scope' => 'nonsense']);

        static::assertSame(2, $tester->getStatusCode());
        static::assertStringContainsString('Invalid scope "nonsense"', $tester->getDisplay());
    }

    public function testChannelApiScopeIsSkippedWhenOnlyAdminIsAvailable(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('admin-tool', $output);
        static::assertStringNotContainsString('Channel API (/channel-api/_mcp)', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewResolvesChannelApiCapabilityAndShowsItsScope(): void
    {
        $channelApiRegistry = new Registry();
        $channelApiRegistry->registerTool(
            new Tool('channel-tool', null, self::inputSchema(), 'Runs in the channel context', null),
            'Acme\\StoreTool',
        );

        $tester = new CommandTester($this->makeCommand(new Registry(), channelApiRegistry: $channelApiRegistry));
        $tester->execute(['name' => 'channel-tool']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Runs in the channel context', $output);
        static::assertStringContainsString('Channel API (/channel-api/_mcp)', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testIntegrationAllowlistDoesNotFilterChannelApiTools(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');
        $registry->registerTool(new Tool('admin-hidden', null, self::inputSchema(), null, null), 'Acme\\AdminHidden');

        $channelApiRegistry = new Registry();
        $channelApiRegistry->registerTool(new Tool('channel-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: ['admin-tool'], resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($registry, $allowlistProvider, channelApiRegistry: $channelApiRegistry));
        $tester->execute(['--integration' => 'CTIA-restricted']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('only apply to the admin scope', $output);
        static::assertStringContainsString('admin-tool', $output);
        static::assertStringNotContainsString('admin-hidden', $output);
        static::assertStringContainsString('channel-tool', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    /**
     * --native hands over to the MCP bundle's own command, which McpDebugCommandCompilerPass renamed
     * so both can keep their own output.
     */
    public function testNativeOptionRunsTheBundleCommand(): void
    {
        $native = new Command(McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME);
        $native->setCode(static function (InputInterface $input, OutputInterface $output): int {
            $output->writeln('native command ran');

            return Command::SUCCESS;
        });

        $application = new Application();
        $application->addCommand($native);
        $application->addCommand($this->makeCommand(new Registry()));

        $tester = new CommandTester($application->find('debug:mcp'));
        $tester->execute(['--native' => true]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('native command ran', $tester->getDisplay());
        static::assertStringNotContainsString('Admin API', $tester->getDisplay());
    }

    /**
     * A capability name given alongside --native is forwarded, so `debug:mcp <name> --native` shows
     * the bundle's detail view for that capability instead of its server list.
     */
    public function testNativeOptionForwardsTheCapabilityName(): void
    {
        $seen = null;
        $native = new Command(McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME);
        $native->addArgument('name', InputArgument::OPTIONAL);
        $native->setCode(static function (InputInterface $input, OutputInterface $output) use (&$seen): int {
            $seen = $input->getArgument('name');

            return Command::SUCCESS;
        });

        $application = new Application();
        $application->addCommand($native);
        $application->addCommand($this->makeCommand(new Registry()));

        $tester = new CommandTester($application->find('debug:mcp'));
        $tester->execute(['name' => 'contena-entity-search', '--native' => true]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertSame('contena-entity-search', $seen);
    }

    public function testNativeOptionFailsWhenTheBundleCommandIsMissing(): void
    {
        $application = new Application();
        $application->addCommand($this->makeCommand(new Registry()));

        $tester = new CommandTester($application->find('debug:mcp'));
        $tester->execute(['--native' => true]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString(McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME, $tester->getDisplay());
    }

    public function testUnassignedCapabilitiesAreReported(): void
    {
        $registry = new Registry();
        $command = new DebugMcpCommand(
            Server::builder()->setRegistry($registry),
            $registry,
            static::createStub(McpAllowlistProvider::class),
            new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider()),
            unassigned: ['tools' => ['Acme\\OrphanTool'], 'prompts' => []],
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('exposed by no server', $output);
        static::assertStringContainsString('Acme\\OrphanTool', $output);
        static::assertStringContainsString('tools', $output);
    }

    public function testNothingIsReportedWhenEveryCapabilityIsAssigned(): void
    {
        $tester = new CommandTester($this->makeCommand(new Registry()));
        $tester->execute([]);

        static::assertStringNotContainsString('exposed by no server', $tester->getDisplay());
    }

    private function makeCommand(
        Registry $registry,
        ?McpAllowlistProvider $allowlistProvider = null,
        ?McpCapabilityCatalog $catalog = null,
        ?Registry $channelApiRegistry = null,
    ): DebugMcpCommand {
        $builder = Server::builder()->setRegistry($registry);

        if ($allowlistProvider === null) {
            $allowlistProvider = static::createStub(McpAllowlistProvider::class);
            $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: null, resources: null, prompts: null));
        }

        $catalog ??= new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        if ($channelApiRegistry === null) {
            return new DebugMcpCommand($builder, $registry, $allowlistProvider, $catalog);
        }

        return new DebugMcpCommand(
            $builder,
            $registry,
            $allowlistProvider,
            $catalog,
            Server::builder()->setRegistry($channelApiRegistry),
            $channelApiRegistry,
            new McpCapabilityCatalog($channelApiRegistry, $this->stubPrivilegeProvider()),
        );
    }

    private function stubPrivilegeProvider(): AppMcpPrivilegeProvider
    {
        $stub = static::createStub(AppMcpPrivilegeProvider::class);
        $stub->method('getAppToolPrivileges')->willReturn([]);

        return $stub;
    }

    /**
     * @return array{type: 'object', properties: array<string, mixed>, required: array<string>|null}
     */
    private static function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'required' => []];
    }
}
