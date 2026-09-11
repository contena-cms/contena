<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\ScheduledTask;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Mcp\McpToolsetSessionStorage;
use Contena\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTaskHandler;
use Mcp\Server\Session\SessionStoreInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\AbstractUid;

/**
 * @internal
 */
#[CoversClass(McpToolsetSessionCleanupTaskHandler::class)]
class McpToolsetSessionCleanupTaskHandlerTest extends TestCase
{
    private const ALIVE_SESSION = 'aaaaaaaa-bbbb-4ccc-8ddd-000000000001';
    private const EXPIRED_SESSION = 'aaaaaaaa-bbbb-4ccc-8ddd-000000000002';
    private const MALFORMED_SESSION = 'not-a-uuid';

    public function testRunDeletesOnlyRowsWhoseSessionIsNotAliveOrIsMalformed(): void
    {
        $storage = static::createStub(McpToolsetSessionStorage::class);
        $storage->method('sessionIds')->willReturn([self::ALIVE_SESSION, self::EXPIRED_SESSION, self::MALFORMED_SESSION]);

        $sessionStore = static::createStub(SessionStoreInterface::class);
        $sessionStore->method('exists')->willReturnCallback(
            static fn (AbstractUid $uuid): bool => $uuid->toRfc4122() === self::ALIVE_SESSION,
        );

        $deleted = [];
        $storage->method('deleteForSession')->willReturnCallback(static function (string $sessionId) use (&$deleted): void {
            $deleted[] = $sessionId;
        });

        $handler = new McpToolsetSessionCleanupTaskHandler(
            static::createStub(EntityRepository::class),
            new NullLogger(),
            $storage,
            $sessionStore,
            static::createStub(SessionStoreInterface::class),
        );

        $handler->run();

        sort($deleted);
        // The alive session is never purged (however old); expired and malformed sessions are.
        static::assertSame([self::EXPIRED_SESSION, self::MALFORMED_SESSION], $deleted);
    }

    /**
     * mcp_toolset_session rows are keyed on the raw Mcp-Session-Id and are not namespaced per
     * endpoint, while each MCP server owns its own session store. A session that only lives in the
     * Channel API store must therefore survive: consulting the Admin API store alone would treat every
     * live Channel API session as abandoned and drop its toolsets.
     */
    public function testRunKeepsASessionThatOnlyExistsInTheChannelApiStore(): void
    {
        $storage = static::createStub(McpToolsetSessionStorage::class);
        $storage->method('sessionIds')->willReturn([self::ALIVE_SESSION, self::EXPIRED_SESSION]);

        $adminStore = static::createStub(SessionStoreInterface::class);
        $adminStore->method('exists')->willReturn(false);

        $channelApiStore = static::createStub(SessionStoreInterface::class);
        $channelApiStore->method('exists')->willReturnCallback(
            static fn (AbstractUid $uuid): bool => $uuid->toRfc4122() === self::ALIVE_SESSION,
        );

        $deleted = [];
        $storage->method('deleteForSession')->willReturnCallback(static function (string $sessionId) use (&$deleted): void {
            $deleted[] = $sessionId;
        });

        $handler = new McpToolsetSessionCleanupTaskHandler(
            static::createStub(EntityRepository::class),
            new NullLogger(),
            $storage,
            $adminStore,
            $channelApiStore,
        );

        $handler->run();

        static::assertSame([self::EXPIRED_SESSION], $deleted);
    }
}
