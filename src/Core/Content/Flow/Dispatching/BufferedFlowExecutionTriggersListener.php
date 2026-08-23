<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * @internal
 */
readonly class BufferedFlowExecutionTriggersListener implements EventSubscriberInterface
{
    public function __construct(private BufferedFlowExecutor $executor, private BufferedFlowQueue $queue)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'triggerBufferedFlowExecution',
            WorkerMessageHandledEvent::class => 'triggerBufferedFlowExecution',
            ConsoleEvents::TERMINATE => 'triggerBufferedFlowExecution',
        ];
    }

    public function triggerBufferedFlowExecution(): void
    {
        if (!$this->queue->isEmpty()) {
            $this->executor->executeBufferedFlows();
        }
    }
}
