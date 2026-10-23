<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;
use Contena\Core\Framework\Mcp\McpToolsetSessionStorage;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Contena\Core\System\Channel\Mcp\Tool\StoreApiToolsetEnableTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(StoreApiToolsetEnableTool::class)]
class StoreApiToolsetEnableToolTest extends TestCase
{
    public function testEnablesToolsetThroughStoreApiSubclass(): void
    {
        $registry = $this->createMock(McpToolsetRegistry::class);
        $registry->expects($this->once())
            ->method('find')
            ->with('store-api')
            ->willReturn([
                'name' => 'store-api',
                'title' => 'Store API tools',
                'description' => 'Store API',
                'tools' => ['contena-store-api-context'],
            ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())
            ->method('enable')
            ->with('session-a', 'store-api');

        $requestStack = new RequestStack();
        $request = Request::create('/store-api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new StoreApiToolsetEnableTool($registry, $storage, $requestStack);
        $result = json_decode($tool('store-api'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('store-api', $result['data']['toolset']['name']);
        static::assertTrue($result['_meta']['listChanged']);
        // Like the admin tool, the store-api variant records intent; the controller emits it.
        static::assertTrue($request->attributes->getBoolean(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE));
    }
}
