<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class BufferedFlowExecutor
{
    private const int MAXIMUM_EXECUTION_DEPTH = 10;

    public function __construct(
        private readonly BufferedFlowQueue $queue,
        private readonly AbstractFlowLoader $loader,
        private readonly FlowFactory $factory,
        private readonly FlowExecutor $executor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function executeBufferedFlows(): void
    {
        $depth = 0;
        while (!$this->queue->isEmpty() && $depth < self::MAXIMUM_EXECUTION_DEPTH) {
            foreach ($this->queue->dequeueFlows() as $buffered) {
                $flowsByEvent = $this->loader->load($buffered->eventContext);
                $holders = $flowsByEvent[$buffered->eventName] ?? [];
                if ($holders !== []) {
                    $this->executor->executeFlows(array_values($holders), $this->factory->restoreBuffered($buffered));
                }
            }
            ++$depth;
        }

        if (!$this->queue->isEmpty()) {
            $events = array_map(static fn (BufferedFlow $flow): string => $flow->eventName, $this->queue->dequeueFlows());
            $this->logger->error('Maximum execution depth reached for buffered flow executor.', ['bufferedEvents' => $events]);
        }
    }
}
