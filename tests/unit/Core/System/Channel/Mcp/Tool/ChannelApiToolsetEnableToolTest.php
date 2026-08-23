<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;
use Contena\Core\Framework\Mcp\McpToolsetSessionStorage;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiToolsetEnableTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ChannelApiToolsetEnableTool::class)]
class ChannelApiToolsetEnableToolTest extends TestCase
{
    public function testEnablesToolsetThroughChannelApiSubclass(): void
    {
        $registry = $this->createMock(McpToolsetRegistry::class);
        $registry->expects($this->once())
            ->method('find')
            ->with('channel-api')
            ->willReturn([
                'name' => 'channel-api',
                'title' => 'Channel API tools',
                'description' => 'Channel API',
                'tools' => ['contena-channel-api-context'],
            ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())
            ->method('enable')
            ->with('session-a', 'channel-api');

        $requestStack = new RequestStack();
        $request = Request::create('/channel-api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new ChannelApiToolsetEnableTool($registry, $storage, $requestStack);
        $result = json_decode($tool('channel-api'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('channel-api', $result['data']['toolset']['name']);
        static::assertTrue($result['_meta']['listChanged']);
        // Like the admin tool, the channel-api variant records intent; the controller emits it.
        static::assertTrue($request->attributes->getBoolean(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE));
    }

    public function testInvokeIsDeclaredOnConcreteClassSoDiscoveryBindsToIt(): void
    {
        // The MCP SDK discoverer binds a tool handler to __invoke's declaring class, and the channel-api
        // service locator keys on the service id (= class). If __invoke were only inherited from
        // ToolsetEnableTool, discovery would bind the handler to the admin base and the channel-api
        // tool would resolve to the wrong (admin-wired) instance.
        $method = new \ReflectionMethod(ChannelApiToolsetEnableTool::class, '__invoke');

        static::assertSame(ChannelApiToolsetEnableTool::class, $method->getDeclaringClass()->getName());
    }
}
