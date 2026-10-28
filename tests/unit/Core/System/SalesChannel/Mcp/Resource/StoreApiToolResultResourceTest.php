<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SalesChannel\Mcp\Resource;

use Mcp\Exception\ResourceNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\ToolResultCacheStorage;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\Mcp\Resource\StoreApiToolResultResource;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiToolResultResource::class)]
class StoreApiToolResultResourceTest extends TestCase
{
    public function testThePointerIsReadForTheCallingSalesChannelContext(): void
    {
        $token = Uuid::randomHex() . '.1790000000.signature';
        $salesChannelContext = Generator::generateSalesChannelContext(token: 'context-token');

        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->once())->method('readFor')
            ->with($token, 'store:' . $salesChannelContext->getSalesChannelId() . ':context-token')
            ->willReturn(['content' => '{"success":true}', 'mimeType' => 'application/json']);

        $request = new Request(server: ['HTTP_SW_CONTEXT_TOKEN' => 'context-token']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $result = (new StoreApiToolResultResource($storage, $requestStack))($token);

        static::assertSame('contena://tool-result/' . $token, $result['uri']);
        static::assertSame('application/json', $result['mimeType']);
        static::assertSame('{"success":true}', $result['text']);
    }

    public function testThePointerIsNotFoundWithoutASalesChannelContext(): void
    {
        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->never())->method('readFor');

        $this->expectException(ResourceNotFoundException::class);

        (new StoreApiToolResultResource($storage, new RequestStack()))(Uuid::randomHex() . '.1790000000.signature');
    }
}
