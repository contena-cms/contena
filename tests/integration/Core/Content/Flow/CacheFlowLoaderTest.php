<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Contena\Core\Content\Flow\Dispatching\CachedFlowLoader;
use Contena\Core\Content\Flow\FlowEvents;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
class CacheFlowLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            FlowEvents::FLOW_WRITTEN_EVENT => 'invalidate',
        ], CachedFlowLoader::getSubscribedEvents());
    }

    public function testClearFlowCache(): void
    {
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');
        EventHookDispatcher::fromContainer(static::getContainer())->on(FlowEvents::FLOW_WRITTEN_EVENT, $listener);

        $flowLoader = static::getContainer()->get(CachedFlowLoader::class);
        $class = new \ReflectionClass($flowLoader);
        $property = $class->getProperty('flows');
        $property->setValue(
            $flowLoader,
            ['abc']
        );

        static::getContainer()->get('flow.repository')->create([[
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
        ]], Context::createDefaultContext());

        static::assertNull($property->getValue($flowLoader));
    }
}
