<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

/**
 * @internal
 */
class BufferedFlowQueue
{
    /**
     * @var list<BufferedFlow>
     */
    private array $bufferedFlows = [];

    public function queueFlow(BufferedFlow $flow): void
    {
        $this->bufferedFlows[] = $flow;
    }

    /**
     * @return list<BufferedFlow>
     */
    public function dequeueFlows(): array
    {
        $flows = $this->bufferedFlows;
        $this->bufferedFlows = [];

        return $flows;
    }

    public function isEmpty(): bool
    {
        return $this->bufferedFlows === [];
    }
}
