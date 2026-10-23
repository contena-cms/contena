<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Contena\Core\Framework\Mcp\Tool\ToolSearchTool;

/**
 * @internal
 */
#[CoversClass(ToolSearchTool::class)]
class ToolSearchToolTest extends TestCase
{
    public function testSearchReturnsMatchingToolDefinitions(): void
    {
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $data = json_decode($tool('read entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('contena-entity-read', $data['data'][0]['tool']['name']);
        static::assertSame(2, $data['_meta']['totalCandidates']);
    }

    public function testResultCarriesToolsetEnableUsageHint(): void
    {
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $data = json_decode($tool('read entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('usage', $data['_meta']);
        static::assertStringContainsString('contena-toolset-enable', $data['_meta']['usage']);
    }

    private function registry(): Registry
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('contena-entity-search', 'Search entities'), 'Acme\\SearchTool');
        $registry->registerTool(self::tool('contena-entity-read', 'Read one entity by ID'), 'Acme\\ReadTool');

        return $registry;
    }

    private static function tool(string $name, string $description): Tool
    {
        return new Tool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => []],
            description: $description,
            annotations: null,
        );
    }
}
