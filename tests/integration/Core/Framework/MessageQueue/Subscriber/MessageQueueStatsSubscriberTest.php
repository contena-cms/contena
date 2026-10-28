<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\MessageQueue\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Increment\AbstractIncrementer;
use Contena\Core\Framework\Increment\IncrementGatewayRegistry;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\MessageQueue\fixtures\BarMessage;
use Contena\Core\Framework\Test\MessageQueue\fixtures\FooMessage;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Tests\Integration\Core\Framework\MessageQueue\fixtures\NoHandlerMessage;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @deprecated tag:v6.8.0 - Test class tests increment-based stats which will be removed
 *
 * @internal
 */
#[Package('framework')]
class MessageQueueStatsSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);
    }

    public function testListener(): void
    {
        /** @var AbstractIncrementer $pool */
        $pool = static::getContainer()
            ->get('contena.increment.gateway.registry')
            ->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        $pool->reset('message_queue_stats');

        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('messenger.bus.test_contena');

        $bus->dispatch(new FooMessage());
        $bus->dispatch(new BarMessage());
        $bus->dispatch(new BarMessage());
        $bus->dispatch(new BarMessage());

        $stats = $pool->list('message_queue_stats');
        static::assertSame(1, $stats[FooMessage::class]['count']);
        static::assertSame(3, $stats[BarMessage::class]['count']);

        $this->runWorker();

        $stats = $pool->list('message_queue_stats');
        static::assertSame(0, $stats[FooMessage::class]['count']);
        static::assertSame(0, $stats[BarMessage::class]['count']);

        $bus->dispatch(new NoHandlerMessage());

        $stats = $pool->list('message_queue_stats');
        static::assertSame(1, $stats[NoHandlerMessage::class]['count']);

        $this->runWorker();
        $stats = $pool->list('message_queue_stats');
        static::assertSame(0, $stats[NoHandlerMessage::class]['count']);
    }
}
