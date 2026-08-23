<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Contena\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
use Contena\Core\Framework\Test\TestCaseHelper\StopWorkerWhenIdleListener;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

trait QueueTestBehaviour
{
    #[Before]
    #[After]
    public function clearQueue(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM messenger_messages');
        $bus = static::getContainer()->get('messenger.bus.test_contena');
        \assert($bus instanceof TraceableMessageBus);
        $bus->reset();
    }

    public function runWorker(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerWhenIdleListener());
        $eventDispatcher->addSubscriber(static::getContainer()->get(MessageQueueStatsSubscriber::class));

        $locator = static::getContainer()->get('messenger.test_receiver_locator');
        \assert($locator instanceof ServiceLocator);

        $async = $locator->get('async');
        \assert($async instanceof ReceiverInterface);

        $bus = static::getContainer()->get('messenger.bus.test_contena');
        \assert($bus instanceof MessageBusInterface);

        $worker = new Worker(['async' => $async], $bus, $eventDispatcher);

        $worker->run([
            'sleep' => 1000,
        ]);
    }

    /**
     * @param class-string $messageClass
     */
    protected function getDispatchedMessageCount(string $messageClass): int
    {
        $bus = static::getContainer()->get('messenger.bus.test_contena');
        \assert($bus instanceof TraceableMessageBus);

        $count = 0;
        foreach ($bus->getDispatchedMessages() as $message) {
            if (isset($message['message']) && $message['message'] instanceof $messageClass) {
                ++$count;
            }
        }

        return $count;
    }

    abstract protected static function getContainer(): ContainerInterface;
}
