<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Resource;

use Mcp\Exception\ResourceNotFoundException;
use Mcp\Schema\JsonRpc\Request as JsonRpcRequest;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Resource\ToolResultResource;
use Contena\Core\Framework\Mcp\ToolResultCacheStorage;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Uid\Uuid as SymfonyUuid;

/**
 * @internal
 */
#[CoversClass(ToolResultResource::class)]
class ToolResultResourceTest extends TestCase
{
    public function testInvokeReturnsStoredResult(): void
    {
        $id = Uuid::randomHex();
        $sessionId = '00000000-0000-0000-0000-000000000001';

        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->once())->method('read')
            ->willReturnCallback(function (string $resultId, string $session) use ($id, $sessionId): array {
                static::assertSame($id, $resultId);
                static::assertSame($sessionId, $session);

                return ['content' => '{"success":true}', 'mimeType' => 'application/json'];
            });

        $resource = new ToolResultResource($storage);
        $result = ($resource)($id, $this->makeContext($sessionId));

        static::assertSame('contena://tool-result/' . $id, $result['uri']);
        static::assertSame('application/json', $result['mimeType']);
        static::assertSame('{"success":true}', $result['text']);
    }

    public function testInvokeThrowsForSessionMismatch(): void
    {
        $storage = static::createStub(ToolResultCacheStorage::class);
        $storage->method('read')->willReturn(null);

        $resource = new ToolResultResource($storage);

        $this->expectException(ResourceNotFoundException::class);
        ($resource)(Uuid::randomHex(), $this->makeContext('00000000-0000-0000-0000-000000000002'));
    }

    public function testInvokeThrowsForUnknownId(): void
    {
        $storage = static::createStub(ToolResultCacheStorage::class);
        $storage->method('read')->willReturn(null);

        $resource = new ToolResultResource($storage);

        $this->expectException(ResourceNotFoundException::class);
        ($resource)(Uuid::randomHex(), $this->makeContext('00000000-0000-0000-0000-000000000001'));
    }

    private function makeContext(string $sessionId): RequestContext
    {
        $session = static::createStub(SessionInterface::class);
        $session->method('getId')->willReturn(SymfonyUuid::fromString($sessionId));

        return new RequestContext($session, static::createStub(JsonRpcRequest::class));
    }
}
