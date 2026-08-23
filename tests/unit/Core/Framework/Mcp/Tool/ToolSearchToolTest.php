<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Tool\AbstractToolSearchTool;
use Contena\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Contena\Core\Framework\Mcp\Tool\ToolSearchTool;

/**
 * @internal
 */
#[CoversClass(ToolSearchTool::class)]
#[CoversClass(AbstractToolSearchTool::class)]
class ToolSearchToolTest extends TestCase
{
    public function testEmbeddedToolDefinitionKeepsEmptyPropertiesAsAnObject(): void
    {
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $json = $tool('read entity');

        static::assertStringContainsString('"properties":{}', $json);
        static::assertStringNotContainsString('"properties":[]', $json);
    }

    private function registry(): Registry
    {
        $registry = new Registry();
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
