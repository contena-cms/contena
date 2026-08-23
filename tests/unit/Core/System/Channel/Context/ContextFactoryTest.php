<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ContextFactory;
use Contena\Core\System\Channel\Event\ContextCreatedEvent;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[CoversClass(ContextFactory::class)]
class ContextFactoryTest extends TestCase
{
    public function testGetContext(): void
    {
        $tenantId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn([
            'channel_default_language_id' => Uuid::randomBytes(),
            'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            'channel_tenant_id' => $tenantId,
        ]);

        $eventDispatcher = new CollectingEventDispatcher();
        $context = new ContextFactory($connection, $eventDispatcher)->getContext(Uuid::randomHex(), [
            ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
        ]);

        $events = $eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(ContextCreatedEvent::class, $events[0]);

        static::assertSame(Defaults::LANGUAGE_SYSTEM, $context->getLanguageId());
        static::assertSame($tenantId, $context->getTenantId());
    }
}
