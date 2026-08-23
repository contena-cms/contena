<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp;

use Mcp\Capability\Registry;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\McpCapabilityCatalog;

/**
 * @internal
 */
#[CoversClass(McpCapabilityCatalog::class)]
class McpCapabilityCatalogTest extends TestCase
{
    public function testEnrichedToolsReturnsEmptyListWhenNoToolsRegistered(): void
    {
        $catalog = new McpCapabilityCatalog(new Registry());

        static::assertSame([], $catalog->enrichedTools());
        static::assertSame(0, $catalog->totalToolCount());
    }

    public function testEnrichedToolsReturnsEntriesSortedByName(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'zeta-tool', 'Zeta');
        $this->registerTool($registry, 'alpha-tool', 'Alpha');
        $this->registerTool($registry, 'middle-tool', 'Middle');

        $catalog = new McpCapabilityCatalog($registry);

        $names = array_column($catalog->enrichedTools(), 'name');
        static::assertSame(['alpha-tool', 'middle-tool', 'zeta-tool'], $names);
    }

    public function testEnrichedToolsAttachesDependenciesAndCorePrivileges(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'contena-entity-delete', 'Delete');

        $catalog = new McpCapabilityCatalog(
            $registry,
            ['contena-entity-delete' => ['contena-entity-search', 'contena-entity-schema']],
            ['contena-entity-delete' => ['static' => [], 'entityParam' => 'entity', 'operations' => ['delete']]],
        );

        $tools = $catalog->enrichedTools();

        static::assertCount(1, $tools);
        static::assertSame(
            ['contena-entity-search', 'contena-entity-schema'],
            $tools[0]['dependencies'],
        );
        static::assertSame(
            ['static' => [], 'entityParam' => 'entity', 'operations' => ['delete']],
            $tools[0]['requiredPrivileges'],
        );
    }

    public function testEnrichedToolsIncludesConfiguredGroup(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'contena-entity-search', 'Search');

        $catalog = new McpCapabilityCatalog(
            $registry,
            [],
            [],
            ['contena-entity-search' => 'catalogue'],
        );

        static::assertSame('catalogue', $catalog->enrichedTools()[0]['group']);
    }

    public function testEnrichedToolsDerivesGroupFromLongestCommonNamePrefix(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'ct-my-plugin-orders', 'List orders');
        $this->registerTool($registry, 'ct-my-plugin-products', 'List products');
        $this->registerTool($registry, 'ct-other-plugin-customers', 'List customers');
        $this->registerTool($registry, 'ct-other-plugin-products', 'List products');

        $catalog = new McpCapabilityCatalog($registry);

        static::assertSame([
            'ct-my-plugin',
            'ct-my-plugin',
            'ct-other-plugin',
            'ct-other-plugin',
        ], array_column($catalog->enrichedTools(), 'group'));
    }

    public function testEnrichedToolsUsesFirstNameSegmentForSingleUnconfiguredTool(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'ct-order-export', 'Export orders');

        $catalog = new McpCapabilityCatalog($registry);

        static::assertSame('ct', $catalog->enrichedTools()[0]['group']);
    }

    public function testFindToolUsesGroupDerivedFromAllRegisteredTools(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'ct-my-plugin-orders', 'List orders');
        $this->registerTool($registry, 'ct-my-plugin-products', 'List products');

        $catalog = new McpCapabilityCatalog($registry);

        static::assertSame('ct-my-plugin', $catalog->findTool('ct-my-plugin-orders')['group'] ?? null);
    }

    public function testEnrichedToolsReturnsNullPrivilegesWhenNoneDeclared(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'no-privs-tool', null);

        $catalog = new McpCapabilityCatalog($registry);

        $tools = $catalog->enrichedTools();

        static::assertNull($tools[0]['requiredPrivileges']);
        static::assertSame([], $tools[0]['dependencies']);
    }

    public function testEnrichedToolsAppliesAllowlistFilter(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', 'A');
        $this->registerTool($registry, 'tool-b', 'B');
        $this->registerTool($registry, 'tool-c', 'C');

        $catalog = new McpCapabilityCatalog($registry);

        $names = array_column($catalog->enrichedTools(['tool-a', 'tool-c']), 'name');
        static::assertSame(['tool-a', 'tool-c'], $names);
    }

    public function testEnrichedToolsWithEmptyAllowlistReturnsNothing(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', 'A');

        $catalog = new McpCapabilityCatalog($registry);

        static::assertSame([], $catalog->enrichedTools([]));
    }

    public function testFindToolReturnsNullForUnknownName(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', 'A');

        $catalog = new McpCapabilityCatalog($registry);

        static::assertNull($catalog->findTool('does-not-exist'));
    }

    public function testFindToolReturnsEnrichedEntry(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', 'A description');

        $catalog = new McpCapabilityCatalog(
            $registry,
            ['tool-a' => ['dep-1']],
        );

        $entry = $catalog->findTool('tool-a');

        static::assertNotNull($entry);
        static::assertSame('tool-a', $entry['name']);
        static::assertSame('A description', $entry['description']);
        static::assertSame(['dep-1'], $entry['dependencies']);
    }

    public function testEnrichedResourcesReturnsSortedList(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new ResourceDefinition('contena://zzz', 'zzz-resource', null, 'Z Resource', null, null, null),
            'Acme\\ZzzResource',
        );
        $registry->registerResource(
            new ResourceDefinition('contena://aaa', 'aaa-resource', null, 'A Resource', null, null, null),
            'Acme\\AaaResource',
        );

        $catalog = new McpCapabilityCatalog($registry);

        $resources = $catalog->enrichedResources();

        static::assertCount(2, $resources);
        static::assertSame('contena://aaa', $resources[0]['uri']);
        static::assertSame('contena://zzz', $resources[1]['uri']);
    }

    public function testEnrichedResourcesAppliesAllowlistFilter(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new ResourceDefinition('contena://aaa', 'aaa-resource', null, 'A', null, null, null),
            'Acme\\AaaResource',
        );
        $registry->registerResource(
            new ResourceDefinition('contena://bbb', 'bbb-resource', null, 'B', null, null, null),
            'Acme\\BbbResource',
        );

        $catalog = new McpCapabilityCatalog($registry);

        $resources = $catalog->enrichedResources(['contena://aaa']);

        static::assertCount(1, $resources);
        static::assertSame('contena://aaa', $resources[0]['uri']);
    }

    public function testEnrichedResourcesWithEmptyAllowlistReturnsNothing(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new ResourceDefinition('contena://aaa', 'aaa-resource', null, 'A', null, null, null),
            'Acme\\AaaResource',
        );

        $catalog = new McpCapabilityCatalog($registry);

        static::assertSame([], $catalog->enrichedResources([]));
    }

    public function testEnrichedPromptsReturnsSortedList(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('zzz-prompt', null, 'Z prompt', []),
            'Acme\\ZzzPrompt',
            [],
        );
        $registry->registerPrompt(
            new Prompt('aaa-prompt', null, 'A prompt', []),
            'Acme\\AaaPrompt',
            [],
        );

        $catalog = new McpCapabilityCatalog($registry);

        $prompts = $catalog->enrichedPrompts();

        static::assertCount(2, $prompts);
        static::assertSame('aaa-prompt', $prompts[0]['name']);
        static::assertSame('zzz-prompt', $prompts[1]['name']);
    }

    public function testEnrichedPromptsAppliesAllowlistFilter(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('prompt-a', null, 'A', []),
            'Acme\\PromptA',
            [],
        );
        $registry->registerPrompt(
            new Prompt('prompt-b', null, 'B', []),
            'Acme\\PromptB',
            [],
        );

        $catalog = new McpCapabilityCatalog($registry);

        $prompts = $catalog->enrichedPrompts(['prompt-a']);

        static::assertCount(1, $prompts);
        static::assertSame('prompt-a', $prompts[0]['name']);
    }

    public function testEnrichedPromptsWithEmptyAllowlistReturnsNothing(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('prompt-a', null, 'A', []),
            'Acme\\PromptA',
            [],
        );

        $catalog = new McpCapabilityCatalog($registry);

        static::assertSame([], $catalog->enrichedPrompts([]));
    }

    public function testEnrichedToolsIncludesTitle(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', 'My Human-Readable Tool', ['type' => 'object', 'properties' => [], 'required' => []], 'desc', null),
            'Acme\\MyTool',
        );

        $catalog = new McpCapabilityCatalog($registry);
        $tools = $catalog->enrichedTools();

        static::assertSame('My Human-Readable Tool', $tools[0]['title']);
    }

    public function testEnrichedToolsIncludesNullTitleWhenNotSet(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', 'desc');

        $catalog = new McpCapabilityCatalog($registry);

        static::assertNull($catalog->enrichedTools()[0]['title']);
    }

    public function testEnrichedPromptsIncludesTitle(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('my-prompt', 'My Human-Readable Prompt', 'desc', []),
            'Acme\\MyPrompt',
            [],
        );

        $catalog = new McpCapabilityCatalog($registry);
        $prompts = $catalog->enrichedPrompts();

        static::assertSame('My Human-Readable Prompt', $prompts[0]['title']);
    }

    public function testEnrichedPromptsIncludesNullTitleWhenNotSet(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('prompt-a', null, 'A', []),
            'Acme\\PromptA',
            [],
        );

        $catalog = new McpCapabilityCatalog($registry);

        static::assertNull($catalog->enrichedPrompts()[0]['title']);
    }

    public function testTotalToolCountReportsRegistrySize(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', null);
        $this->registerTool($registry, 'tool-b', null);

        $catalog = new McpCapabilityCatalog($registry);

        static::assertSame(2, $catalog->totalToolCount());
    }

    private function registerTool(Registry $registry, string $name, ?string $description): void
    {
        $registry->registerTool(
            new Tool($name, null, ['type' => 'object', 'properties' => [], 'required' => []], $description, null),
            'Acme\\' . str_replace('-', '', ucwords($name, '-')),
        );
    }
}
