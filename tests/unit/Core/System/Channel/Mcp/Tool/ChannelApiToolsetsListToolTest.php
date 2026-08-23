<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;
use Contena\Core\Framework\Mcp\McpToolsetSessionStorage;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiToolsetsListTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ChannelApiToolsetsListTool::class)]
class ChannelApiToolsetsListToolTest extends TestCase
{
    public function testListsToolsetsThroughChannelApiSubclass(): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('toolsets')->willReturn([
            [
                'name' => 'channel-api',
                'title' => 'Channel API tools',
                'description' => 'Channel API',
                'tools' => ['contena-channel-api-context'],
            ],
        ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())
            ->method('enabledToolsets')
            ->with('session-a')
            ->willReturn(['channel-api']);

        $requestStack = new RequestStack();
        $request = Request::create('/channel-api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new ChannelApiToolsetsListTool($registry, $storage, $requestStack);
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('channel-api', $result['data']['toolsets'][0]['name']);
        static::assertTrue($result['data']['toolsets'][0]['enabled']);
        static::assertSame('tool-groups', $result['_meta']['taxonomy']);
    }

    public function testInvokeIsDeclaredOnConcreteClassSoDiscoveryBindsToIt(): void
    {
        // The MCP SDK discoverer binds a tool handler to __invoke's declaring class, and the channel-api
        // service locator keys on the service id (= class). If __invoke were only inherited from
        // ToolsetsListTool, discovery would bind the handler to the admin base and the channel-api
        // tool would resolve to the wrong (admin-wired) instance.
        $method = new \ReflectionMethod(ChannelApiToolsetsListTool::class, '__invoke');

        static::assertSame(ChannelApiToolsetsListTool::class, $method->getDeclaringClass()->getName());
    }
}
