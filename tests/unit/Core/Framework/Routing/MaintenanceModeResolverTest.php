<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Routing\Event\MaintenanceModeRequestEvent;
use Contena\Core\Framework\Routing\MaintenanceModeResolver;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(MaintenanceModeResolver::class)]
class MaintenanceModeResolverTest extends TestCase
{
    public function testIsClientAllowedTriggersEventAndReturnsFalseForDisallowedClient(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MaintenanceModeRequestEvent::class));

        $resolver = new MaintenanceModeResolver($eventDispatcher);
        static::assertFalse($resolver->isClientAllowed(new Request(server: ['REMOTE_ADDR' => '192.168.0.4']), []));
    }

    public function testIsClientAllowedTriggersEventAndReturnsTrueForAllowedClient(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MaintenanceModeRequestEvent::class));

        $resolver = new MaintenanceModeResolver($eventDispatcher);
        static::assertTrue($resolver->isClientAllowed(new Request(server: ['REMOTE_ADDR' => '192.168.0.4']), ['192.168.0.4']));
    }

    public function testClientIsAllowedButEventDisallowsIt(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MaintenanceModeRequestEvent::class))
            ->willReturnCallback(static function (MaintenanceModeRequestEvent $event) {
                static::assertTrue($event->isClientAllowed());
                $event->disallowClient();

                return $event;
            });

        $resolver = new MaintenanceModeResolver($eventDispatcher);
        static::assertFalse($resolver->isClientAllowed(new Request(server: ['REMOTE_ADDR' => '192.168.0.4']), ['192.168.0.4']));
    }

    public function testClientIsDisallowedButEventAllowsIt(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(MaintenanceModeRequestEvent::class))
            ->willReturnCallback(static function (MaintenanceModeRequestEvent $event) {
                static::assertFalse($event->isClientAllowed());
                $event->allowClient();

                return $event;
            });

        $resolver = new MaintenanceModeResolver($eventDispatcher);
        static::assertTrue($resolver->isClientAllowed(new Request(server: ['REMOTE_ADDR' => '192.168.0.4']), []));
    }

    public function testGetIpsUsesAllowlistAttribute(): void
    {
        $resolver = new MaintenanceModeResolver(static::createStub(EventDispatcherInterface::class));

        $request = new Request(server: ['REMOTE_ADDR' => '192.168.0.4']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_MAINTENANCE, true);
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_MAINTENANCE_IP_ALLOWLIST,
            json_encode(['192.168.0.4'], \JSON_THROW_ON_ERROR)
        );

        static::assertFalse($resolver->isMaintenanceRequest($request));
    }
}
