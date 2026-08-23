<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\Api;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\MessageQueue\MessageQueueException;
use Contena\Core\Framework\MessageQueue\Subscriber\CountHandledMessagesListener;
use Contena\Core\Framework\MessageQueue\Subscriber\EarlyReturnMessagesListener;
use Contena\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Util\MemorySizeCalculator;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ConsumeMessagesController extends AbstractController
{
    /**
     * @param ServiceLocator<ReceiverInterface> $receiverLocator
     *
     * @internal
     */
    public function __construct(
        private readonly ServiceLocator $receiverLocator,
        private readonly MessageBusInterface $bus,
        private readonly StopWorkerOnRestartSignalListener $stopWorkerOnRestartSignalListener,
        private readonly EarlyReturnMessagesListener $earlyReturnListener,
        private readonly MessageQueueStatsSubscriber $statsSubscriber,
        private readonly string $defaultTransportName,
        private readonly string $memoryLimit,
        private readonly int $pollInterval,
        private readonly LockFactory $lockFactory
    ) {
    }

    #[Route(path: '/api/_action/message-queue/consume', name: 'api.action.message-queue.consume', methods: ['POST'])]
    public function consumeMessages(Request $request): JsonResponse
    {
        $receiverName = RequestParamHelper::get($request, 'receiver');

        if (!$receiverName || !$this->receiverLocator->has($receiverName)) {
            throw MessageQueueException::validReceiverNameNotProvided();
        }

        $consumerLock = $this->lockFactory->createLock('message_queue_consume_' . $receiverName);

        if (!$consumerLock->acquire()) {
            throw MessageQueueException::workerIsLocked($receiverName);
        }

        $receiver = $this->receiverLocator->get($receiverName);

        $workerDispatcher = new EventDispatcher();
        $listener = new CountHandledMessagesListener();
        $workerDispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($this->pollInterval));
        $workerDispatcher->addSubscriber($listener);
        $workerDispatcher->addSubscriber($this->statsSubscriber);
        $workerDispatcher->addSubscriber($this->stopWorkerOnRestartSignalListener);
        $workerDispatcher->addSubscriber($this->earlyReturnListener);

        if ($this->memoryLimit !== '-1') {
            $workerDispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener(
                MemorySizeCalculator::convertToBytes($this->memoryLimit)
            ));
        }

        $worker = new Worker([$this->defaultTransportName => $receiver], $this->bus, $workerDispatcher);

        $worker->run();

        $consumerLock->release();

        return new JsonResponse(['handledMessages' => $listener->getHandledMessages()]);
    }
}
