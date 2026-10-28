<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Event\ContenaAccountLoginEvent;
use Contena\Core\Framework\Store\Event\ContenaAccountLogoutEvent;
use Contena\Core\Service\ServiceLifecycle;
use Contena\Core\Service\Subscriber\ContenaAccountSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContenaAccountSubscriber::class)]
class ContenaAccountSubscriberTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testReevaluatesServicesOnLogin(): void
    {
        $event = new ContenaAccountLoginEvent($this->context);

        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle
            ->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        (new ContenaAccountSubscriber($serviceLifecycle))->reevaluateServices($event);
    }

    public function testReevaluatesServicesOnLogout(): void
    {
        $event = new ContenaAccountLogoutEvent($this->context);

        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle
            ->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        (new ContenaAccountSubscriber($serviceLifecycle))->reevaluateServices($event);
    }

    public function testSubscribedEvents(): void
    {
        $events = ContenaAccountSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(ContenaAccountLoginEvent::class, $events);
        static::assertArrayHasKey(ContenaAccountLogoutEvent::class, $events);
        static::assertSame('reevaluateServices', $events[ContenaAccountLoginEvent::class]);
        static::assertSame('reevaluateServices', $events[ContenaAccountLogoutEvent::class]);
    }
}
