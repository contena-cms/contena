<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Mcp\Xml;

use Contena\Core\Framework\App\Mcp\Mcp;
use Contena\Core\Framework\App\Mcp\Xml\McpTool;
use Contena\Core\Framework\App\Mcp\Xml\McpTools;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(McpTools::class)]
class McpToolsTest extends TestCase
{
    public function testFromXmlParsesMultipleTools(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertNotNull($tools);
        static::assertCount(2, $tools->getTools());

        static::assertContainsOnlyInstancesOf(McpTool::class, $tools->getTools());
    }

    public function testGetToolsReturnsToolsInOrder(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertNotNull($tools);

        $names = array_map(
            static fn (McpTool $t) => $t->getName(),
            $tools->getTools(),
        );

        static::assertSame(['sync-blogs', 'media-check'], $names);
    }

    public function testFromArrayCreatesTools(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'test-tool',
            'url' => 'https://example.com',
            'label' => ['en-GB' => 'Test'],
            'description' => [],
        ]);

        $tools = McpTools::fromArray(['tools' => [$tool]]);

        static::assertCount(1, $tools->getTools());
        static::assertSame('test-tool', $tools->getTools()[0]->getName());
    }
}
