<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Contena\Core\Framework\Mcp\McpCapabilityCatalog;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;

/**
 * @internal
 */
#[CoversClass(McpToolsetRegistry::class)]
class McpToolsetRegistryTest extends TestCase
{
    public function testBuildsToolsetsFromExplicitToolGroups(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'contena-entity-search',
            'contena-entity-read',
            'contena-order-state',
            'ungrouped-tool',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            toolGroups: [
                McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'discovery',
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'discovery',
                'contena-entity-search' => 'entity',
                'contena-entity-read' => 'entity',
                'contena-order-state' => 'order',
            ],
        ));

        $toolsets = $toolsetRegistry->toolsets();
        $toolsetsByName = array_column($toolsets, null, 'name');

        // A tool without an explicit group uses its first name segment as an enable-able toolset.
        static::assertSame(['entity', 'order', 'ungrouped'], array_keys($toolsetsByName));
        static::assertSame(['contena-entity-read', 'contena-entity-search'], $toolsetsByName['entity']['tools']);
        static::assertSame(['contena-order-state'], $toolsetsByName['order']['tools']);
        static::assertSame(['ungrouped-tool'], $toolsetsByName['ungrouped']['tools']);
        static::assertSame('Entity tools', $toolsetsByName['entity']['title']);
        static::assertSame('Ungrouped tools', $toolsetsByName['ungrouped']['title']);
        static::assertSame('Tools explicitly assigned to the "entity" MCP tool group.', $toolsetsByName['entity']['description']);
    }

    public function testFindReturnsToolsetByName(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'contena-entity-search',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            toolGroups: [
                McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'discovery',
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'discovery',
                'contena-entity-search' => 'entity',
            ],
        ));

        static::assertSame('entity', $toolsetRegistry->find('entity')['name'] ?? null);
        static::assertNull($toolsetRegistry->find('discovery'));
        static::assertNull($toolsetRegistry->find('missing'));
    }

    public function testAdvertisedToolsReturnsEnabledToolsetTools(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::LIST_TOOLSETS_TOOL,
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'contena-entity-search',
            'contena-entity-read',
            'contena-order-state',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(new McpCapabilityCatalog(
            $registry,
            toolGroups: [
                McpToolsetRegistry::LIST_TOOLSETS_TOOL => 'discovery',
                McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'discovery',
                'contena-entity-search' => 'entity',
                'contena-entity-read' => 'entity',
                'contena-order-state' => 'order',
            ],
        ));

        static::assertSame([], $toolsetRegistry->advertisedTools([]));

        static::assertSame(
            [
                'contena-entity-read',
                'contena-entity-search',
            ],
            $toolsetRegistry->advertisedTools(['entity']),
        );

        static::assertSame(
            [
                'contena-entity-read',
                'contena-entity-search',
                'contena-order-state',
            ],
            $toolsetRegistry->advertisedTools(['entity', 'order']),
        );
    }

    public function testToolsetsAreScopedToTheCurrentAllowlist(): void
    {
        $registry = $this->buildRegistry([
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
            'contena-entity-search',
            'contena-entity-read',
            'contena-order-state',
        ]);

        $toolsetRegistry = new McpToolsetRegistry(
            new McpCapabilityCatalog(
                $registry,
                toolGroups: [
                    McpToolsetRegistry::ENABLE_TOOLSET_TOOL => 'discovery',
                    'contena-entity-search' => 'entity',
                    'contena-entity-read' => 'entity',
                    'contena-order-state' => 'order',
                ],
            ),
            $this->stubAllowlistProvider(['contena-entity-search']),
        );

        $toolsetsByName = array_column($toolsetRegistry->toolsets(), null, 'name');

        // Discovery stays inside the allowlist: only the allowed tool surfaces. The denied
        // "entity-read" and the entirely-denied "order" toolset never leak through list/enable.
        static::assertSame(['entity'], array_keys($toolsetsByName));
        static::assertSame(['contena-entity-search'], $toolsetsByName['entity']['tools']);
        static::assertNull($toolsetRegistry->find('order'));
        static::assertSame([], $toolsetRegistry->advertisedTools(['order']));
    }

    /**
     * @param list<string> $toolNames
     */
    private function buildRegistry(array $toolNames): Registry
    {
        $registry = new Registry();

        foreach ($toolNames as $toolName) {
            $registry->registerTool(
                new Tool($toolName, null, ['type' => 'object', 'properties' => [], 'required' => []], null, null),
                'Acme\\' . str_replace('-', '', ucwords($toolName, '-')),
            );
        }

        return $registry;
    }

    /**
     * @param list<string>|null $tools
     */
    private function stubAllowlistProvider(?array $tools): McpAllowlistProvider
    {
        $stub = static::createStub(McpAllowlistProvider::class);
        $stub->method('toolsForCurrentRequest')->willReturn($tools);

        return $stub;
    }
}
