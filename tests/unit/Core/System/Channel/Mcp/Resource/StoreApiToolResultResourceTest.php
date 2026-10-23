<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Mcp\Resource;

use Mcp\Exception\ResourceNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\ToolResultCacheStorage;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Mcp\Resource\StoreApiToolResultResource;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(StoreApiToolResultResource::class)]
class StoreApiToolResultResourceTest extends TestCase
{
    public function testThePointerIsReadForTheCallingChannelContext(): void
    {
        $token = Uuid::randomHex() . '.1790000000.signature';
        $channelContext = Generator::generateChannelContext(token: 'context-token');

        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->once())->method('readFor')
            ->with($token, 'store:' . $channelContext->getChannelId() . ':context-token')
            ->willReturn(['content' => '{"success":true}', 'mimeType' => 'application/json']);

        $request = new Request(server: ['HTTP_SW_CONTEXT_TOKEN' => 'context-token']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $result = (new StoreApiToolResultResource($storage, $requestStack))($token);

        static::assertSame('contena://tool-result/' . $token, $result['uri']);
        static::assertSame('application/json', $result['mimeType']);
        static::assertSame('{"success":true}', $result['text']);
    }

    public function testThePointerIsNotFoundWithoutAChannelContext(): void
    {
        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->never())->method('readFor');

        $this->expectException(ResourceNotFoundException::class);

        (new StoreApiToolResultResource($storage, new RequestStack()))(Uuid::randomHex() . '.1790000000.signature');
    }
}
