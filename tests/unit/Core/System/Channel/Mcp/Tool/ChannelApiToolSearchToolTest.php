<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Tool\AbstractToolSearchTool;
use Contena\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiToolSearchTool;

/**
 * @internal
 */
#[CoversClass(ChannelApiToolSearchTool::class)]
#[CoversClass(AbstractToolSearchTool::class)]
class ChannelApiToolSearchToolTest extends TestCase
{
    public function testSearchReturnsChannelApiToolDefinitions(): void
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('contena-channel-api-language-list', 'List languages'), 'Acme\\LanguageListTool');

        $tool = new ChannelApiToolSearchTool($registry, new ToolSearch());

        $data = json_decode($tool('language'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('contena-channel-api-language-list', $data['data'][0]['tool']['name']);
    }

    public function testResultCarriesToolsetEnableUsageHint(): void
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('contena-channel-api-language-list', 'List languages'), 'Acme\\LanguageListTool');

        $tool = new ChannelApiToolSearchTool($registry, new ToolSearch());

        $data = json_decode($tool('language'), true, 512, \JSON_THROW_ON_ERROR);

        // Channel API uses progressive disclosure, so tool-search nudges toward the enable path.
        static::assertArrayHasKey('usage', $data['_meta']);
        static::assertStringContainsString('contena-toolset-enable', $data['_meta']['usage']);
    }

    public function testInvokeIsDeclaredOnConcreteClassSoDiscoveryBindsToIt(): void
    {
        // The MCP SDK discoverer binds a tool handler to __invoke's declaring class. If __invoke
        // were only inherited from AbstractToolSearchTool, discovery would bind the handler to the
        // non-instantiable abstract base and the tool would fail at runtime.
        $method = new \ReflectionMethod(ChannelApiToolSearchTool::class, '__invoke');

        static::assertSame(ChannelApiToolSearchTool::class, $method->getDeclaringClass()->getName());
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
