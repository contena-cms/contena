<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ScheduledTaskExecutorCompilerPass;
use Contena\Core\Framework\MessageQueue\MessageQueueException;

abstract class ScheduledTaskHandler
{
    private ?ScheduledTaskExecutor $scheduledTaskExecutor = null;

    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        protected EntityRepository $scheduledTaskRepository,
        protected readonly LoggerInterface $exceptionLogger,
    ) {
    }

    public function __invoke(ScheduledTask $task): void
    {
        if ($this->scheduledTaskExecutor === null) {
            throw MessageQueueException::scheduledTaskExecutorNotSet(static::class);
        }

        $this->scheduledTaskExecutor->execute($this, $task);
    }

    /**
     * @internal injected by the {@see ScheduledTaskExecutorCompilerPass}
     */
    public function setScheduledTaskExecutor(ScheduledTaskExecutor $scheduledTaskExecutor): void
    {
        $this->scheduledTaskExecutor = $scheduledTaskExecutor;
    }

    abstract public function run(): void;
}
