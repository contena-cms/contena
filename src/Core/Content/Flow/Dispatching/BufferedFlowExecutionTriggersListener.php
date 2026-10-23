<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

use Psr\Container\ContainerInterface;
use Contena\Core\Framework\Feature;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @internal not intended for decoration or replacement
 *
 * @final
 */
readonly class BufferedFlowExecutionTriggersListener implements EventSubscriberInterface, ServiceSubscriberInterface
{
    public function __construct(
        private ContainerInterface $container,
        private BufferedFlowQueue $bufferedFlowQueue,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        if (!Feature::isActive('FLOW_EXECUTION_AFTER_BUSINESS_PROCESS')) {
            return [];
        }

        // Run buffered flows after any execution environment finishes one unit of work
        return [
            KernelEvents::TERMINATE => 'triggerBufferedFlowExecution',
            WorkerMessageHandledEvent::class => 'triggerBufferedFlowExecution',
            ConsoleEvents::TERMINATE => 'triggerBufferedFlowExecution',
        ];
    }

    public function triggerBufferedFlowExecution(): void
    {
        if ($this->bufferedFlowQueue->isEmpty()) {
            return;
        }

        $this->container->get(BufferedFlowExecutor::class)->executeBufferedFlows();
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedServices(): array
    {
        return [
            BufferedFlowExecutor::class,
        ];
    }
}
